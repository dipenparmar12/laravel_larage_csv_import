<?php

namespace Dipenparmar12\ImportCsv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ImportCsv
{
    /**
     * @var array
     */
    public $csv = [];
    /**
     * @var bool
     */
    protected $truncate = false;
    /**
     * @var bool
     */
    protected $foreignKeyCheck = true;
    /**
     * @var string
     */
    protected $delimiter = ',';
    /**
     * @var int
     */
    protected $fkState = 1;
    /**
     * @var bool
     */
    protected $directory = false;
    /**
     * @var bool
     */
    protected $get = true;
    /**
     * @var array
     */
    protected $csvPaths = [];

    /**
     * CsvImport constructor.
     *
     * @param array $csv
     */
    public function __construct(array $csv = [])
    {
        $this->csv = $csv;
    }

    /**
     * @param array $csv
     * @return bool|Collection
     * @throws Throwable
     */
    public function insert(array $csv = [])
    {
        if (count($csv) > 0) {
            $this->csv = $csv;
        }

        $table_data = [];
        foreach ($this->csv as $file_name => $model) {
            $model = (new $model());
            $csvData = $this->parseCsv($this->getFilePath($file_name));
            DB::transaction(function () use ($model, $csvData) {
                if ($model instanceof Model) {
                    if ($this->truncate) {
                        $model->truncate();
                    }
                    $model->insert($csvData);
                }
            });
            $table_data[$file_name] = $csvData;
        }
        return ($this->get == true) ? $this->getCollection($table_data) : true;
    }

    /**
     * Get Array of data from CSV file handler
     *
     * @param $full_path
     *
     * @return array
     */
    public function parseCsv($full_path)
    {
        $header = null;
        $table_data = [];
        if ($file_handler = $this->openCsv($full_path)) {
            while (($csv_row = fgetcsv($file_handler, 1000, $this->delimiter)) !== false) {
                if (!$header) {
                    $header = array_map('trim', $csv_row); // trim white space to each element
                } else {
                    foreach ($csv_row as $key => $field) {
                        $field = trim($field);
                        $csv_row[$key] = ($field == "" or $field == " " or $field == null) ? NULL : $field;
                    }
                    $table_data[] = (count($header) === count($csv_row)) ? array_combine($header, $csv_row) : null ?? null;
                }
            }
            fclose($file_handler);
        }
        return array_filter($table_data);
    }

    /**
     * Open Csv file from given path
     *
     * @param string $file_path
     *
     * @return false|resource
     */
    protected function openCsv(string $file_path)
    {
        if (!$file_path || !is_readable($file_path) || !file_exists($file_path)) {
            Log::error("CSV Failed:" . $file_path . " does not exist or is not readable.");
            throw new FileException("CSV Failed:  " . $file_path . " does not exist or is not readable.");
        }
        return fopen($file_path, 'r');
    }

    /**
     * Get full path of csv File
     *
     * @param $file_name
     * @param null $path
     *
     * @return string
     */
    protected function getFilePath($file_name)
    {
        if ($this->directory) {
            $file_path = rtrim($this->directory, "/") . "/" . $file_name . ".csv";
        } else {
            $file_path = base_path("database/csv_import/$file_name.csv");
        }
        $this->csvPaths[] = $file_path;
        return $file_path;
    }

    /**
     * Get collection of row data after operations
     *
     * @param $table_data
     *
     * @return Collection
     */
    protected function getCollection($table_data)
    {
        return collect([
            "associated_csv" => $this->csv,
            "files" => $this->csvPaths,
            "delimiter" => $this->delimiter,
            'truncate' => $this->truncate,
            "foreignKeyChecks" => $this->foreignKeyCheck,
            "data" => $table_data,
        ]);
    }

    /**
     * TODO::WIP->FKC
     * FK check
     *
     * @param bool $state
     *
     * @return CsvImportable
     */
    public function foreignKeyCheck(bool $state = true)
    {
        $this->fkState = DB::select("SELECT @@FOREIGN_KEY_CHECKS as fkc")[0]->fkc ? 1 : 0;
        $this->foreignKeyCheck = ($state === true) ? 1 : 0;
        $fk_state = $this->foreignKeyCheck ? 1 : 0;
        DB::statement("SET FOREIGN_KEY_CHECKS=$fk_state");
        DB::statement("SET FOREIGN_KEY_CHECKS=$this->foreignKeyCheck");
        return $this;
    }

    /**
     * @param $csvFilePaths
     *
     * @return Collection
     */
    public function parse($csvFilePaths)
    {
        if (is_array($csvFilePaths)) {
            $csvData = [];
            foreach ($csvFilePaths as $key => $value) {
                if ($this->isAssoc($csvFilePaths)) {
                    $csvData[$value] = $this->parseCsv($this->getFilePath($value));
                } else {
                    $csvData[$key] = $this->parseCsv($this->getFilePath($key));
                }
            }
        } else {
            $csvData[$csvFilePaths] = $this->parseCsv($this->getFilePath($csvFilePaths));
        }
        return collect($csvData);
    }

    /**
     * Determine array is associative or sequential?
     *
     * @param array $array
     *
     * @return bool
     */
    protected function isAssoc(array $array)
    {
        if (array() === $array) return false;
        /*if (!is_array($array)) { return false; }*/
        if (count($array) <= 0) {
            return true;
        }
        return array_unique(array_map("is_string", array_keys($array))) !== array(true);
    }

    /**
     * Truncate table before insertions
     *
     * @param bool $status
     *
     * @return $this
     */
    public function truncate(bool $status = false)
    {
        $this->truncate = ($status === true) ? 1 : 0;
        return $this;
    }

    /**
     * @param bool $status
     * @return $this
     */
    public function get(bool $status = false)
    {
        $this->get = ($status === true) ? 1 : 0;
        return $this;
    }

    /**
     * @param string $dir
     *
     * @return $this
     */
    public function directory(string $dir)
    {
        if ($dir) {
            $this->directory = $dir;
        }
        return $this;
    }
}
