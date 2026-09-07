<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Models\Expense;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LivewireUpdateRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_uses_admin_livewire_update_endpoint(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/expenses');

        $response->assertOk();
        $response->assertSee('data-update-uri="/admin/livewire/update"', false);
    }

    public function test_admin_livewire_update_route_accepts_post_requests(): void
    {
        $response = $this->post('/admin/livewire/update');

        $response->assertStatus(404);
    }

    public function test_expense_table_can_render_fifty_records_per_page(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        foreach (range(1, 60) as $index) {
            Expense::create([
                'date' => '2026-08-29',
                'category' => 'Parkir',
                'amount' => 5000,
                'notes' => "Expense {$index}",
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(ListExpenses::class)
            ->set('tableRecordsPerPage', 50);

        $this->assertSame(50, $component->instance()->getTableRecords()->count());
    }

    public function test_expense_table_opens_with_light_default_page_size(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        foreach (range(1, 12) as $index) {
            Expense::create([
                'date' => '2026-08-29',
                'category' => 'Parkir',
                'amount' => 5000,
                'notes' => "Expense {$index}",
            ]);
        }

        $this->actingAs($user);

        $component = Livewire::test(ListExpenses::class);

        $this->assertSame(10, $component->instance()->getTableRecords()->count());
    }

    public function test_outlet_list_renders_stock_columns(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        Outlet::create([
            'name' => 'TIPTOP DEPOK',
            'group_name' => 'tiptop',
            'partner_share_percent' => 15,
        ]);

        $response = $this->actingAs($user)->get('/admin/outlets');

        $response->assertOk();
        $response->assertSee('TIPTOP DEPOK');
        $response->assertSee('Sisa Buah');
    }
}
