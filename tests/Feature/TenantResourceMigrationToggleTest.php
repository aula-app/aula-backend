<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every field on the tenant form must name a real column.
 *
 * A tenant keeps unknown attributes in a `data` blob instead of rejecting
 * them, so a field named anything else still saves without error, and the
 * switch appears to work while the app goes on reading NULL from the column
 * it actually cares about. That is how the migration toggle shipped broken.
 *
 * This reads the resource's source rather than its schema because building
 * the schema boots a Filament panel, which needs more memory than the test
 * container has.
 */
class TenantResourceMigrationToggleTest extends TestCase
{
    /**
     * Fields that deliberately do not correspond to a column: display-only
     * placeholders, and inputs the resource itself unpacks before saving.
     *
     * @var list<string>
     */
    private const array VIRTUAL_FIELDS = [
        'idp_migration_state',
        'admin_password',
        'admin1_password',
        'admin2_password',
    ];

    public function test_the_migration_toggle_names_the_column_it_writes(): void
    {
        $this->assertStringContainsString(
            "Toggle::make('idp_migration_status')",
            $this->source(),
            'The migration toggle must be named for its column, or it saves into the data blob instead.',
        );
    }

    public function test_every_form_field_names_a_real_column(): void
    {
        $columns = Schema::connection(config('tenancy.database.central_connection'))
            ->getColumnListing('tenants');

        preg_match_all(
            "/(?:Toggle|TextInput|Select|Textarea|DateTimePicker|Checkbox)::make\('([^']+)'\)/",
            $this->source(),
            $matches,
        );

        $this->assertNotEmpty($matches[1], 'No form fields were found; the parse is out of date.');

        foreach (array_unique($matches[1]) as $field) {
            if (in_array($field, self::VIRTUAL_FIELDS, true)) {
                continue;
            }

            $this->assertContains(
                $field,
                $columns,
                "The form field '{$field}' is not a tenants column, so saving it writes to the data blob. "
                .'Rename it to its column, or add it to VIRTUAL_FIELDS if that is deliberate.',
            );
        }
    }

    public function test_the_migration_states_the_toggle_switches_between_exist(): void
    {
        // The toggle turns the column on to "flagged" and off to null; the
        // rest of the state machine belongs to the app.
        $this->assertSame('flagged', Tenant::IDP_MIGRATION_FLAGGED);
    }

    private function source(): string
    {
        return (string) file_get_contents(app_path('Filament/Resources/TenantResource.php'));
    }
}
