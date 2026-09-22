<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeRepository extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:repository {name : The name of the repository class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new repository class';

    protected $files;

    public function __construct()
    {
        parent::__construct();
        $this->files = new Filesystem;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $repositoryPath = app_path('Repositories');
        $filePath = $repositoryPath.'/'.$name.'.php';

        if ($this->files->exists($filePath)) {
            $this->error("Repository {$name} already exists!");

            return 1;
        }

        if (! $this->files->isDirectory($repositoryPath)) {
            $this->files->makeDirectory($repositoryPath, 0755, true);
        }

        $stub = $this->getStub($name);

        $this->files->put($filePath, $stub);

        $this->info("Repository {$name} created successfully!");

        return 0;
    }

    protected function getStub($name)
    {
        return <<<EOT
<?php

namespace App\Repositories;

use App\Models\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class {$name}
{
    protected Model \$model;

    public function __construct(Model \$model)
    {
        \$this->model = \$model;
    }

    /**
     * Get all records.
     */
    public function all()
    {
        return \$this->model->all();
    }

    /**
     * Find a record by ID.
     */
    public function find(\$id)
    {
        return \$this->model->findOrFail(\$id);
    }

    /**
     * Create a new record.
     */
    public function create(array \$data)
    {
        return \$this->model->create(\$data);
    }

    /**
     * Update a record by ID.
     */
    public function update(\$id, array \$data)
    {
        \$record = \$this->model->findOrFail(\$id);
        \$record->update(\$data);
        return \$record;
    }

    /**
     * Delete a record by ID.
     */
    public function delete(\$id)
    {
        \$record = \$this->model->findOrFail(\$id);
        return \$record->delete();
    }
}
EOT;
    }
}
