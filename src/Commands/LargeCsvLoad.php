<?php

namespace Dipenparmar12\ImportCsv\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LargeCsvLoad extends Command
{
    public $secure_file_priv_path;
    public $field_terminated_by;
    public $line_terminated_by;
    public $truncate;
    public $csv_dir;
    public $csv_ignore_lines;
    public $input_file_extension;
    public $csv_files;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv:load 
                    {--field_terminated_by=, : Fields Terminated}
                    {--line_terminated_by= : Line Terminated}
                    {--truncate :  Truncate table before inserting new records.}
                    {--ignore_lines : Ignore number of lines before import. (if we want to ignore csv headers)}
                    {--csv_dir= :  Csv directory.}
                    {--file_extension :  file_extension.}
                    { --force : Force the operation to run when database table not found, and skip file import }
    ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        DB::statement("set global local_infile = 1;");
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // dump($this->options());
        // dump($this->arguments());
        // dd($this);

        $this->field_terminated_by = $this->option('field_terminated_by') ?: config('csv_import.field_terminated_by');
        $this->line_terminated_by = $this->option('line_terminated_by') ?: config('csv_import.line_terminated_by');
        $this->truncate = $this->option('truncate') ?: config('csv_import.truncate');
        $this->csv_dir = $this->option('csv_dir') ?: config('csv_import.csv_dir');
        $this->csv_ignore_lines = $this->option('ignore_lines') ?: config('csv_import.ignore_lines');
        $this->input_file_extension = $this->option('file_extension') ?: config('csv_import.file_extension');
        $this->csv_files = glob("$this->csv_dir/*.$this->input_file_extension");

        $imported_csv = [];
        $skipped_csv = [];

        foreach ($this->csv_files as $file_path) {
            $table_name = $this->get_table_name_from_file_name(basename($file_path, ".$this->input_file_extension"));
            if (!$this->option('force') && !Schema::hasTable($table_name)
                && !$this->confirm("Table '$table_name' not found in database, Are sure to skip this file ?")
            ) {
                $this->line('Command canceled.');
                return 0;
            }
        }

        foreach ($this->csv_files as $file_path) {
            $table = $this->get_table_name_from_file_name(basename($file_path, ".$this->input_file_extension"));
            if (Schema::hasTable($table)) {
                if ($this->truncate) {
                    DB::table($table)->truncate();
                }

                DB::statement("SET FOREIGN_KEY_CHECKS = 0");

                $import_query = sprintf(
                    "
                        LOAD DATA LOCAL INFILE '%s' INTO TABLE `%s`
                            FIELDS TERMINATED BY '$this->field_terminated_by' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '$this->line_terminated_by' IGNORE $this->csv_ignore_lines LINES            
                     ;"
                    , addslashes($file_path), $table
                );

                $db = DB::connection()->getpdo();
                $db->exec($import_query);

                /*
                 *
                $DB_HOST = env('DB_HOST');
                $DB_USERNAME = env('DB_USERNAME');
                $DB_PASSWORD = env('DB_PASSWORD', NULL);
                $DB_DATABASE = env('DB_DATABASE');
                $import_cmd = "";
                $import_cmd .= " mysqlimport -v --local";
                // $import .= " --fields-enclosed-by='\"' ";
                // $import .= " --fields-escaped-by='\' ";
                $import_cmd .= " --fields-terminated-by='$field_terminated_by'";
                $import_cmd .= " --lines-terminated-by='$line_terminated_by'  ";
                $import_cmd .= " --ignore-lines=$csv_ignore_lines";
                $import_cmd .= " -u{$DB_USERNAME} -h$DB_HOST $DB_DATABASE ";
                $import_cmd .= " -p{$DB_PASSWORD} ";
                $import_cmd .= " '$file_path'";
                $import_cmd .= " 2> /dev/null";]
                shell_exec($import_cmd);*/

                DB::statement("SET FOREIGN_KEY_CHECKS = 1");
                $imported_csv[] = $table . ".$this->input_file_extension";

            } else {
                $skipped_csv[] = $table . ".$this->input_file_extension";
            }
        }

        dump([
            "successful_imported_csv" => $imported_csv,
            "skipped_csv" => $skipped_csv,
            "field_terminated_by" => $this->field_terminated_by,
            "line_terminated_by" => $this->line_terminated_by,
            "truncate" => $this->truncate,
            "csv_dir" => $this->csv_dir
        ]);

        return 1;
    }

    public function get_table_name_from_file_name($path)
    {
        return basename($path, ".$this->input_file_extension");
    }

    public function get_csv_headings($path, $str = true)
    {
        $headings_array = @fgetcsv(fopen($path, 'rb'));
        if ($str) {
            return implode(',', $headings_array) ?? "";
        }

        return $headings_array;
    }

    public function __destruct()
    {
        DB::statement("set global local_infile = 0;");
    }
}
