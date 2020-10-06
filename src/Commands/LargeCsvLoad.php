<?php

namespace Dipenparmar12\ImportCsv\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LargeCsvLoad extends Command
{
    public $secure_file_priv_path;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csv:load 
                    {--field_terminated_by=, : Fields Terminated}
                    {--line_terminated_by=\n : Line Terminated}
                    {--truncate :  Truncate table before inserting new records.}
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

        $field_terminated_by = $this->option('field_terminated_by') ? $this->option('field_terminated_by') : config('csv_import.field_terminated_by');
        $line_terminated_by = $this->option('line_terminated_by') ? $this->option('line_terminated_by') : config('csv_import.line_terminated_by');
        $truncate = $this->option('truncate') ? $this->option('truncate') : config('csv_import.truncate');
        $csv_dir = $this->option('csv_dir') ? $this->option('csv_dir') : config('csv_import.csv_dir');
        $input_file_extension = $this->option('file_extension') ? $this->option('file_extension') : config('csv_import.file_extension');
        $csv_files = glob(base_path("$csv_dir/*.$input_file_extension"));
        $imported_csv = [];
        $skipped_csv = [];

        foreach ($csv_files as $file_path) {
            $table_name = basename($file_path, ".$input_file_extension");
            if (!$this->option('force') && !Schema::hasTable($table_name)
                && !$this->confirm("Table '$table_name' not found in database, Are sure to skip this file ?")
            ) {
                $this->line('Command canceled.');
                return 0;
            }
        }

        $DB_HOST = env('DB_HOST');
        $DB_USERNAME = env('DB_USERNAME');
        $DB_PASSWORD = env('DB_PASSWORD');
        $DB_DATABASE = env('DB_DATABASE', NULL);

        DB::statement("set global local_infile = 1;");
        foreach ($csv_files as $file_path) {
            $table = basename($file_path, ".$input_file_extension");
            if (Schema::hasTable($table)) {

                if ($truncate) {
                    DB::statement("SET FOREIGN_KEY_CHECKS = 0");
                    DB::table($table)->truncate();
                }

                $import = "mysqlimport -v --local";
                // $import .= " --fields-enclosed-by='\"' ";
                // $import .= " --fields-escaped-by='\' ";
                $import .= " --fields-terminated-by='$field_terminated_by'";
                $import .= " --lines-terminated-by='$line_terminated_by'  ";
                $import .= " -u{$DB_USERNAME} -h$DB_HOST $DB_DATABASE ";
                $import .= " -p{$DB_PASSWORD} ";
                $import .= " '$file_path'";
                exec($import);
                if ($truncate) {
                    DB::statement("SET FOREIGN_KEY_CHECKS = 1");
                }
                $imported_csv[] = $table . ".$input_file_extension";
            } else {
                $skipped_csv[] = $table . ".$input_file_extension";
            }
        }

        dump([
            "successful_imported_csv" => $imported_csv,
            "skipped_csv" => $skipped_csv,
            "field_terminated_by" => $field_terminated_by,
            "line_terminated_by" => $line_terminated_by,
            "truncate" => $truncate,
            "csv_dir" => $csv_dir
        ]);

        return 0;
    }
}
