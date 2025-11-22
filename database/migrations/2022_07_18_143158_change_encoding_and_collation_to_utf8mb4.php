<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private $tables = [
        'acceptances',
        'bios',
        'conferences',
        'dismissed_conferences',
        'failed_jobs',
        'favorites',
        'migrations',
        'oauth_access_tokens',
        'oauth_auth_codes',
        'oauth_clients',
        'oauth_personal_access_clients',
        'oauth_refresh_tokens',
        'password_resets',
        'rejections',
        'submissions',
        'talk_reactions',
        'talk_revisions',
        'talks',
        'users',
        'users_social',
    ];

    private $enumColumns = [
        'talk_revisions.level' => ['beginner', 'intermediate', 'advanced'],
    ];

    public function up(): void
    {
        $this->changeAll('utf8mb4', 'utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        $this->changeAll('utf8', 'utf8_unicode_ci');
    }

    private function changeAll($encoding, $collation)
    {
        if (config('app.env') === 'testing') {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::statement('ALTER DATABASE '.config('database.connections.mysql.database')." CHARACTER SET = {$encoding} COLLATE = {$collation}");

        foreach ($this->tables as $table) {
            DB::statement("ALTER TABLE {$table} CONVERT TO CHARACTER SET {$encoding} COLLATE {$collation};");
            foreach (Schema::getColumnListing($table) as $column) {
                $columnType = $this->columnType($table, $column);
                if (collect(['string', 'text', 'enum'])->doesntContain($columnType)) {
                    continue;
                }

                $dataType = $this->columnDataType($table, $column, $columnType);
                DB::statement("ALTER TABLE {$table} MODIFY {$column} {$dataType} CHARACTER SET {$encoding} COLLATE {$collation};");
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    private function columnType($table, $column)
    {
        if ($this->isEnum($table, $column)) {
            return 'enum';
        }

        return Schema::getColumnType($table, $column);
    }

    private function columnDataType($table, $column, $columnType)
    {
        if ($columnType === 'text') {
            return 'TEXT';
        }

        if ($this->isEnum($table, $column)) {
            return "enum('".implode("','", $this->enumColumns["{$table}.{$column}"])."')";
        }

        $length = DB::connection()->getDoctrineColumn($table, $column)->getLength();

        return "VARCHAR({$length})";
    }

    private function isEnum($table, $column)
    {
        return array_key_exists("{$table}.{$column}", $this->enumColumns);
    }
};
