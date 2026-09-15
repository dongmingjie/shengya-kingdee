<?php

namespace Shengya\Kingdee\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Shengya\KingdeeLaravel\Models\KingdeeDeferredIncomeApplication;
use Shengya\KingdeeLaravel\Models\KingdeeDeferredIncomeApplicationLine;
use Shengya\KingdeeLaravel\Models\KingdeeInvoiceApplication;
use Shengya\KingdeeLaravel\Models\KingdeeInvoiceApplicationLine;
use Shengya\KingdeeLaravel\Services\KingdeeDeferredIncomeAllocator;
use Shengya\KingdeeLaravel\Services\KingdeeInvoiceLineSynchronizer;

final class KingdeeDeferredIncomeAllocatorTest extends TestCase
{
    /** @var Capsule */
    private $database;

    protected function setUp(): void
    {
        parent::setUp();

        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository([
            'kingdee' => [
                'documents' => [
                    'connection' => 'testing',
                    'tables' => [
                        'invoice_applications' => 'kd_invoice_applications',
                        'invoice_application_lines' => 'kd_invoice_application_lines',
                        'deferred_income_applications' => 'kd_deferred_income_applications',
                        'deferred_income_application_lines' => 'kd_deferred_income_application_lines',
                    ],
                ],
            ],
        ]));
        // ValidationException::withMessages 依赖 Validator 门面，测试容器需提供与 Laravel 一致的绑定。
        Facade::setFacadeApplication($container);
        $container->instance('validator', new ValidatorFactory(new Translator(new ArrayLoader(), 'zh_CN'), $container));

        $this->database = new Capsule($container);
        $this->database->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ], 'testing');
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->createTables();
    }

    public function testItPersistsEveryInvoiceDetailAsAnIndependentLine(): void
    {
        $invoice = new KingdeeInvoiceApplication();
        $invoice->id = 10;
        (new KingdeeInvoiceLineSynchronizer())->sync($invoice, [
            [
                'material_number' => 'FW001', 'quantity' => 1, 'tax_rate' => 6,
                'tax_price' => 700, 'amount_without_tax' => 660.38, 'tax_amount' => 39.62,
                'confirm_income' => false,
            ],
            [
                'material_number' => 'FW002', 'quantity' => 1, 'tax_rate' => 6,
                'tax_price' => 300, 'amount_without_tax' => 283.02, 'tax_amount' => 16.98,
                'confirm_income' => true,
            ],
        ]);

        $this->assertSame(2, KingdeeInvoiceApplicationLine::query()->count());
        $this->assertSame(
            ['FW001', 'FW002'],
            KingdeeInvoiceApplicationLine::query()->orderBy('line_no')->pluck('material_number')->all()
        );
    }

    public function testItMapsDeferrableLinesBySequenceAndIgnoresKingdeeAmounts(): void
    {
        $invoice = KingdeeInvoiceApplication::forceCreate(['status' => 'approved']);
        $synchronizer = new KingdeeInvoiceLineSynchronizer();
        $synchronizer->sync($invoice, [
            [
                'material_number' => 'FW001', 'quantity' => 1, 'tax_rate' => 6,
                'tax_price' => 700, 'amount_without_tax' => 660.38, 'tax_amount' => 39.62,
                'confirm_income' => false,
            ],
            [
                'material_number' => 'FW002', 'quantity' => 1, 'tax_rate' => 6,
                'tax_price' => 300, 'amount_without_tax' => 283.02, 'tax_amount' => 16.98,
                'confirm_income' => true,
            ],
        ]);

        $synchronizer->syncDeferrableEntryIds($invoice, ['Result' => ['Result' => [
            'FEntityDetail' => [
                [
                    'FENTRYID' => 9002,
                    'FSEQ' => 2,
                    'FMATERIALID' => ['FNUMBER' => 'FW002'],
                    'FPRICEQTY' => 1,
                    'FENTRYTAXRATE' => 6,
                    'FTAXPRICE' => 300,
                ],
                [
                    'FENTRYID' => 9001,
                    'FSEQ' => 1,
                    'FMATERIALID' => ['FNUMBER' => 'DIFFERENT-MATERIAL'],
                    'FPRICEQTY' => 1,
                    'FENTRYTAXRATE' => 13,
                    // 金蝶金额可能被插件重算，不得参与组件递延额度判断。
                    'FTAXPRICE' => 999.99,
                ],
                // 金蝶可能附带组件本地未持久化的额外行，不能据此整单拒绝递延。
                [
                    'FENTRYID' => 9999,
                    'FSEQ' => 3,
                    'FMATERIALID' => ['FNUMBER' => 'EXTRA'],
                    'FPRICEQTY' => 1,
                    'FENTRYTAXRATE' => 0,
                    'FTAXPRICE' => 1,
                ],
            ],
        ]]]);

        $lines = KingdeeInvoiceApplicationLine::query()->orderBy('line_no')->get();
        $this->assertSame('9001', $lines[0]->kingdee_entry_id);
        $this->assertNull($lines[1]->kingdee_entry_id);
    }

    public function testItRejectsConfirmedIncomeLines(): void
    {
        $line = $this->invoiceLine(true, 100);

        $this->expectException(ValidationException::class);
        (new KingdeeDeferredIncomeAllocator())->prepare(1, [[
            'invoice_application_line_id' => $line->id,
            'amount_without_tax' => 10,
        ]]);
    }

    public function testItCountsActiveApplicationsButReleasesRejectedQuota(): void
    {
        $line = $this->invoiceLine(false, 100);
        $active = $this->application('auditing');
        $rejected = $this->application('rejected');
        $this->allocation($active, $line, 70);
        $this->allocation($rejected, $line, 20);

        $prepared = (new KingdeeDeferredIncomeAllocator())->prepare(1, [[
            'invoice_application_line_id' => $line->id,
            'amount_without_tax' => 30,
        ]]);
        $this->assertSame(30.0, $prepared[0]['amount_without_tax']);
        $this->assertSame(31.8, $prepared[0]['amount_with_tax']);

        $this->expectException(ValidationException::class);
        (new KingdeeDeferredIncomeAllocator())->prepare(1, [[
            'invoice_application_line_id' => $line->id,
            'amount_without_tax' => 30.01,
        ]]);
    }

    private function invoiceLine(bool $confirmIncome, float $amount): KingdeeInvoiceApplicationLine
    {
        return KingdeeInvoiceApplicationLine::forceCreate([
            'invoice_application_id' => 1,
            'line_no' => 1,
            'material_number' => 'FW001',
            'quantity' => 1,
            'tax_rate' => 6,
            'tax_price' => round($amount * 1.06, 2),
            'amount_with_tax' => round($amount * 1.06, 2),
            'amount_without_tax' => $amount,
            'tax_amount' => round($amount * 0.06, 2),
            'confirm_income' => $confirmIncome,
        ]);
    }

    private function application(string $status): KingdeeDeferredIncomeApplication
    {
        return KingdeeDeferredIncomeApplication::forceCreate(['status' => $status]);
    }

    private function allocation(
        KingdeeDeferredIncomeApplication $application,
        KingdeeInvoiceApplicationLine $line,
        float $amount
    ): void {
        KingdeeDeferredIncomeApplicationLine::forceCreate([
            'deferred_income_application_id' => $application->id,
            'invoice_application_line_id' => $line->id,
            'line_no' => 1,
            'amount_without_tax' => $amount,
            'amount_with_tax' => round($amount * 1.06, 2),
            'tax_amount' => round($amount * 0.06, 2),
        ]);
    }

    private function createTables(): void
    {
        $schema = $this->database->getConnection('testing')->getSchemaBuilder();
        $schema->create('kd_invoice_application_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('invoice_application_id');
            $table->integer('line_no');
            $table->string('kingdee_entry_id')->nullable();
            $table->string('material_number');
            $table->string('material_name')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('tax_rate', 8, 4);
            $table->decimal('tax_price', 18, 6);
            $table->decimal('amount_with_tax', 18, 2);
            $table->decimal('amount_without_tax', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->integer('settlement_category')->nullable();
            $table->boolean('confirm_income');
            $table->timestamps();
        });
        $schema->create('kd_deferred_income_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status');
            $table->timestamps();
        });
        $schema->create('kd_invoice_applications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('status')->nullable();
            $table->timestamps();
        });
        $schema->create('kd_deferred_income_application_lines', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('deferred_income_application_id');
            $table->integer('invoice_application_line_id');
            $table->integer('line_no');
            $table->decimal('amount_with_tax', 18, 2);
            $table->decimal('amount_without_tax', 18, 2);
            $table->decimal('tax_amount', 18, 2);
            $table->timestamps();
        });
    }
}
