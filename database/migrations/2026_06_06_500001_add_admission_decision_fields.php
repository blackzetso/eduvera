<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admission_applications', 'decision')) {
            Schema::table('admission_applications', function (Blueprint $table) {
                $table->string('decision', 20)->nullable()->after('status');
                $table->timestamp('decision_at')->nullable()->after('decision');
                $table->unsignedBigInteger('decision_by_user_id')->nullable()->after('decision_at');
                $table->unsignedBigInteger('converted_by_user_id')->nullable()->after('converted_at');

                $table->foreign('decision_by_user_id', 'adm_apps_dec_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->foreign('converted_by_user_id', 'adm_apps_conv_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index('decision', 'adm_apps_decision_idx');
            });
        } else {
            if ($this->indexExists('admission_applications', 'admission_applications_decision_index')) {
                Schema::table('admission_applications', function (Blueprint $table) {
                    $table->dropIndex('admission_applications_decision_index');
                });
            }

            if (! $this->indexExists('admission_applications', 'adm_apps_decision_idx')) {
                Schema::table('admission_applications', function (Blueprint $table) {
                    $table->index('decision', 'adm_apps_decision_idx');
                });
            }
        }

        if (! Schema::hasTable('admission_decision_histories')) {
            Schema::create('admission_decision_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('admission_application_id');
                $table->string('from_decision', 20)->nullable();
                $table->string('to_decision', 20);
                $table->text('reason')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('performed_by_user_id')->nullable();
                $table->timestamp('effective_at');
                $table->timestamps();

                $table->foreign('admission_application_id', 'adm_dec_hist_app_fk')
                    ->references('id')
                    ->on('admission_applications')
                    ->cascadeOnDelete();

                $table->foreign('performed_by_user_id', 'adm_dec_hist_perf_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();

                $table->index(
                    ['admission_application_id', 'effective_at'],
                    'adm_dec_hist_app_eff_idx'
                );
            });
        } elseif (! $this->indexExists('admission_decision_histories', 'adm_dec_hist_app_eff_idx')) {
            Schema::table('admission_decision_histories', function (Blueprint $table) {
                $table->index(
                    ['admission_application_id', 'effective_at'],
                    'adm_dec_hist_app_eff_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_decision_histories');

        if (Schema::hasColumn('admission_applications', 'decision')) {
            Schema::table('admission_applications', function (Blueprint $table) {
                if ($this->foreignKeyExists('admission_applications', 'adm_apps_dec_by_fk')) {
                    $table->dropForeign('adm_apps_dec_by_fk');
                } elseif ($this->foreignKeyExists('admission_applications', 'admission_applications_decision_by_user_id_foreign')) {
                    $table->dropForeign('admission_applications_decision_by_user_id_foreign');
                }

                if ($this->foreignKeyExists('admission_applications', 'adm_apps_conv_by_fk')) {
                    $table->dropForeign('adm_apps_conv_by_fk');
                } elseif ($this->foreignKeyExists('admission_applications', 'admission_applications_converted_by_user_id_foreign')) {
                    $table->dropForeign('admission_applications_converted_by_user_id_foreign');
                }

                if ($this->indexExists('admission_applications', 'adm_apps_decision_idx')) {
                    $table->dropIndex('adm_apps_decision_idx');
                } elseif ($this->indexExists('admission_applications', 'admission_applications_decision_index')) {
                    $table->dropIndex('admission_applications_decision_index');
                }

                $table->dropColumn([
                    'decision',
                    'decision_at',
                    'decision_by_user_id',
                    'converted_by_user_id',
                ]);
            });
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        return collect(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]))->isNotEmpty();
    }

    protected function foreignKeyExists(string $table, string $name): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', $database)
            ->where('table_name', $table)
            ->where('constraint_name', $name)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};
