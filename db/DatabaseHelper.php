<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseHelper extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:helper';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Database helper';

    /**
     * Selected options
     *
     * @var array
     */
    protected $selected_options = '';

    /**
     * All the table lists
     *
     * @var array
     */
    protected $tables = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->getTables();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->mainOptions();

        $this->line('See you again 🙋');
    }

    /**
     * Main options
     */
    private function mainOptions()
    {
        $this->line("--------------------------------------------");

        if (config('app.env') == 'production') {
            $choice = $this->choice('Main options or', ['tables', 'table-columns', 'quit'], 2);
        } else {
            $choice = $this->choice('Main options or', [
                'tables',
                'table-columns',
                'truncate-table',
                'drop-table',
                'add-column',
                'drop-column',
                'change-column',
                'fresh-table',
                'quit'
            ], 8);
        }

        $this->selected_options = $choice;

        switch ($choice) {
            case 'tables':
                $this->tableLists();
                break;

            case 'table-columns':
                $this->tableColumns();
                break;

            case 'truncate-table':
                $this->truncateTable();
                break;

            case 'drop-table':
                $this->dropTable();
                break;

            case 'add-column':
                $this->addColumn();
                break;

            case 'drop-column':
                $this->dropColumn();
                break;

            case 'change-column':
                $this->changeColumn();
                break;

            case 'fresh-table':
                $this->freshTable();
                break;

            case 'quit':
                $this->line('See you again 🙋');
                die();
                break;
        }
    }

    /**
     * Table lists
     */
    private function tableLists()
    {
        $dbName     = config('database.connections.mysql.database');
        $tables     = DB::select("SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = :databaseName", ['databaseName' => $dbName]);

        $tablesArray    = json_decode(json_encode($tables), true);
        $tableLists     = collect($tablesArray)->map(function ($item, $key) {
            return ['id' => $key + 1] + $item;
        });

        $this->table(['Position', 'Table Name', 'Table Rows'], $tableLists);

        if ($this->confirm('Main options?')) {
            return $this->mainOptions();
        }
    }

    /**
     * Table columns
     */
    private function tableColumns($tableName = null)
    {
        $tableName      = $tableName ?? $this->getTableName();
        $columns        = DB::select("DESCRIBE $tableName");
        $columnsArray   = json_decode(json_encode($columns), true);
        $columnLists    = collect($columnsArray)->map(function ($item, $key) {
            return ['id' => $key + 1] + $item;
        });

        $this->table(['Position', 'Name', 'Data Type', 'Null', 'Key', 'Default', 'Extra'], $columnLists);

        if ($this->confirm('Table Lists?')) {
            return $this->tableColumns();
        }
        if ($this->confirm('Main options?')) {
            return $this->mainOptions();
        }
    }

    /**
     * Truncate table
     */
    private function truncateTable()
    {
        $tableName = $this->getTableName();

        if ($tableName == 'migrations') {
            $this->error("You can't truncate migrations table");
            return $this->truncateTable();
        }

        if ($tableName && $this->confirm("Are you sure want to truncate *{$tableName}* table?")) {
            DB::table($tableName)->truncate();
            $this->info("✅ {$tableName} table truncate successfully");
            $this->line("");
        }
        return $this->truncateTable();
    }

    /**
     * Drop table
     */
    private function dropTable()
    {
        $this->getTables();
        $tableName = $this->getTableName();

        if ($tableName == 'migrations') {
            $this->error("You can't drop migrations table");
            return $this->dropTable();
        }

        if ($tableName && $this->confirm("Are you sure want to drop *{$tableName}* table?")) {
            $drop = DB::statement("DROP TABLE $tableName");
            if ($drop) {
                DB::table('migrations')->where('migration', 'LIKE', "%{$tableName}%")->delete();
            }
            $this->info("✅ {$tableName} table drop successfully");
        }
        return $this->dropTable();
    }

    /**
     * Add Column 
     */
    private function addColumn()
    {
        $tableName      = $this->getTableName();
        $columns        = Schema::getColumnListing($tableName);

        $columnName     = $this->askColumnName();
        $dataType       = $this->getColumnDataType();

        $afterColumn    = $this->choice('After column?', $columns, 1);
        $afterColumn    = $afterColumn ? "AFTER $afterColumn" : '';

        $nullColumn     = $this->choice('Nullable?', ['YES', 'NO'], 0);
        $nullColumn     = $nullColumn == 'YES' ? 'NULL' : '';

        $defaultValue   = $this->ask('Default Value ?');
        $defaultValue   = $defaultValue ? "DEFAULT '$defaultValue'" : '';

        DB::statement("ALTER TABLE $tableName ADD COLUMN $columnName $dataType $nullColumn $defaultValue $afterColumn");
        $this->info("✅ {$columnName} added successfully in $tableName table");
        $this->line("");

        return $this->tableColumns($tableName);
    }

    /**
     * Drop Column 
     */
    private function dropColumn($tableName = null)
    {
        $tableName  = $tableName ?? $this->getTableName();
        $columnName = $this->getColumnName($tableName, [$this, "dropColumn"]);

        if ($this->confirm("Are you sure want to drop *{$columnName}* column?")) {
            DB::statement("ALTER TABLE $tableName DROP COLUMN $columnName");
            $this->info("✅ {$columnName} column drop successfully");
        }
        return $this->dropColumn($tableName);
    }

    /**
     * Change data type
     */
    private function changeColumn($tableName = null)
    {
        $tableName  = $tableName ?? $this->getTableName();
        $columnName = $this->getColumnName($tableName, [$this, "changeColumn"]);
        $dataType   = $this->getColumnDataType();

        $nullColumn     = $this->choice('Nullable?', ['YES', 'NO'], 0);
        $nullColumn     = $nullColumn == 'YES' ? 'NULL' : '';

        $defaultValue   = $this->ask('Default Value ?');
        $defaultValue   = $defaultValue ? "DEFAULT '$defaultValue'" : '';

        if ($this->confirm('Change column name?')) {
            $newcolumnName  = $this->askColumnName();
            DB::statement("ALTER TABLE $tableName CHANGE $columnName $newcolumnName $dataType $nullColumn $defaultValue");
            $columnName     = $newcolumnName;
        }

        if ($this->confirm('Are you sure want to modify the column?')) {
            DB::statement("ALTER TABLE $tableName MODIFY $columnName $dataType $nullColumn $defaultValue");
            $this->info("✅ $columnName datatype change to $dataType");
            $this->line('');
        }
        return $this->changeColumn($tableName);
    }

    /**
     * Fresh table
     */
    private function freshTable()
    {
        if ($this->confirm('Are you sure want to fresh all the tables?')) {
            Artisan::call('migrate:fresh');
            $this->info("✅ fresh all the tables");
            $this->line('');
        }
        $this->getTables();
        $this->mainOptions();
    }

    /**
     * -----------------------
     * primary methods
     * -----------------------
     */

    /**
     * Get all the tables
     */
    function getTables()
    {
        $tables = DB::select('SHOW TABLES');
        $table_lists = array_map('current', $tables);
        array_push($table_lists, 'Main options');

        $this->tables = $table_lists;
    }

    /**
     * Get table name
     * 
     * @return string $tableName
     */
    private function getTableName()
    {
        $this->line("--------------------------------------------");
        $this->info("➤ Selected option: **{$this->selected_options}**");
        $this->line("--------------------------------------------");

        $position = count($this->tables) - 1;
        $tableName = $this->choice('Say the key position for select table? or', $this->tables, $position);

        if ($tableName == 'Main options') {
            return $this->mainOptions();
        }

        $this->line("--------------------------------------------");
        $this->info("➤ Selected table: **{$tableName}**");
        $this->line("--------------------------------------------");
        return $tableName;
    }

    /**
     * Get column name
     * 
     * @return string $columnName
     */
    private function getColumnName($tableName, callable $callback)
    {
        try {
            $columns        = DB::select("DESCRIBE $tableName");
            $columnsArray   = json_decode(json_encode($columns), true);
            $columnLists    = collect($columnsArray)->map(fn ($row) => $row['Field'] . ' -- ' . $row['Type'])->toArray();
            array_push($columnLists, 'Back');

            $position   = count($columnLists) - 1;
            $columnName = $this->choice('Select column?', $columnLists, $position);
            $columnName = Str::before($columnName, ' --');

            if ($columnName == 'id') {
                $this->error("You can't change/drop/modify without id");
                return $callback($tableName);
            } else if ($columnName == 'Back') {
                return $callback();
            }

            return $columnName;
        } catch (Exception $ex) {
            return $this->mainOptions();
        }
    }

    // ask column name
    private function askColumnName()
    {
        $columnName = $this->ask('Column Name ?');
        if (!$columnName) {
            $this->askColumnName();
        }
        $columnName = Str::snake($columnName);
        $columnName = preg_replace('/[^A-Za-z_]/', '', $columnName);
        $columnName = Str::lower($columnName);

        return $columnName;
    }

    /**
     * Get column data type
     * 
     * @return string $dataType
     */
    private function getColumnDataType()
    {
        $dataType   = $this->choice('Select data type?', ['VARCHAR', 'INT', 'TINYINT', 'TEXT', 'CHAR', 'DATE'], 0);
        $length     = $this->getColumnLength($dataType);

        return $length ? "$dataType($length)" : $dataType;
    }

    /**
     * Get column length
     * 
     * @return int $length
     */
    private function getColumnLength($type)
    {
        switch ($type) {
            case 'VARCHAR':
                $length = 191;
                break;

            case 'INT':
                $length = 11;
                break;

            case 'TINYINT':
                $length = 4;
                break;

            case 'CHAR':
                $length = 10;
                break;

            default:
                return 0;
                break;
        }

        return $this->askColumnLength($length);
    }

    // ask for column length
    private function askColumnLength($length)
    {
        if ($this->confirm('Set the column length?')) {
            $length = (int) $this->ask('Length ?');

            if ($length <= 0) {
                return $this->askLength($length);
            }
        }

        return $length;
    }
}
