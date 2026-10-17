<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_export_contains_only_own_rows_and_neutralises_formulas(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        $mine = $this->goalFor($me, ['name' => '=HYPERLINK("http://evil.test","click")']);
        $theirs = $this->goalFor($other, ['name' => 'Their goal']);
        $this->entry($me, $mine, 'deposit', '100');
        $this->entry($me, $mine, 'withdrawal', '40');
        $this->entry($other, $theirs, 'deposit', '999');

        $response = $this->actingAs($me)->get('/transactions/export');
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringNotContainsString('Their goal', $csv);
        $this->assertStringNotContainsString('999', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'Formula must be prefixed with an apostrophe');
        $this->assertStringContainsString(',-40.00,', $csv, 'Negative numbers stay numeric');
    }

    public function test_expense_export_is_scoped_and_safe(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        foreach ([[$me, '@SUM(1+1)'], [$other, 'other users secret']] as [$u, $note]) {
            $e = new Expense(['amount' => '5', 'category' => 'food', 'expense_date' => now()->toDateString(), 'note' => $note]);
            $e->user_id = $u->id;
            $e->save();
        }

        $csv = $this->actingAs($me)->get('/expenses/export')->streamedContent();
        $this->assertStringContainsString("'@SUM(1+1)", $csv);
        $this->assertStringNotContainsString('other users secret', $csv);
    }

    public function test_report_export_and_print_view(): void
    {
        $me = User::factory()->create();
        $this->entry($me, null, 'deposit', '10');
        $this->actingAs($me)->get('/reports/export')->assertOk();
        $this->get('/reports/print')->assertOk()->assertSee('annual summary');
        $this->get('/reports')->assertOk();
    }

    public function test_csv_safe_helper(): void
    {
        $this->assertSame("'=1+1", Csv::safe('=1+1'));
        $this->assertSame("'+cmd", Csv::safe('+cmd'));
        $this->assertSame("'-2+3", Csv::safe('-2+3'));
        $this->assertSame("'@foo", Csv::safe('@foo'));
        $this->assertSame("'\tx", Csv::safe("\tx"));
        $this->assertSame('-150.25', Csv::safe('-150.25'));
        $this->assertSame('Groceries', Csv::safe('Groceries'));
        $this->assertSame('', Csv::safe(null));
    }
}
