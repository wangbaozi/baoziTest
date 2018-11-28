<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Home\CronController;

class Cron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:down';

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
     * @return mixed
     */
    public function handle()
    {

        $cron =  new CronController();


        $cron->down();


    }
}
