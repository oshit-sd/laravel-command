<?php

namespace App\Console\Commands\Redis;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class RedisCacheKey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Redis cache key forget successfully';

    /**
     * Cache keys prefix
     * Search Key
     *
     * @var string
     */
    protected $prefix;
    protected $search_key = '*';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        // make redis cache key prefix
        $app_name       = Str::replace('-', "_", Str::slug(config('app.name')));
        $this->prefix   = "{$app_name}_database_{$app_name}_cache:";
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->choiceOptions();

        $this->line('See you again 🙋');
    }

    /**
     * Choice options
     */
    private function choiceOptions()
    {
        $this->search_key = '';
        $choice = $this->choice('Main options', ['lists', 'search', 'keys', 'flush', 'quit']);

        switch ($choice) {
            case 'lists':
                $this->keysTable();
                break;

            case 'search':
                $this->search_key = $this->ask('Key name?');
                $this->keysTable();
                break;

            case 'keys':
                $this->cacheKeyForget($this->search_key);
                break;

            case 'flush':
                $this->flushKeys();
                break;

            case 'quit':
                return true;
                break;
        }

        return true;
    }

    /**
     * Cache key lisis
     * show the table in terminal
     */
    private function keysTable()
    {
        $keys = $this->getCacheKeys($this->search_key);

        if (count($keys) < 1) {
            $this->line('No keys are available');
        } else {
            $this->table(['Position', 'Key Name'], $keys);
        }

        if ($this->confirm('Main options?')) {
            $this->choiceOptions();
        }
    }

    /**
     * Specific cache key delete
     */
    private function cacheKeyForget()
    {
        $cache_keys = Redis::connection('cache')->keys("*{$this->search_key}*");
        $cache_keys = collect($cache_keys)->map(fn ($value) => Str::replace($this->prefix, '', $value))->toArray();
        array_push($cache_keys, 'Main options');

        $defaultPosition = count($cache_keys) - 1;
        $keyName = $this->choice('Say the key position for delete? or enter', $cache_keys, $defaultPosition, 2);

        if ($keyName == 'Main options') {
            return $this->choiceOptions();
        }

        Cache::store('redis')->forget($keyName);

        $this->info("✅ {$keyName} delete successfully");

        $this->cacheKeyForget($this->search_key);
    }

    /**
     * Flash all the keys
     */
    private function flushKeys()
    {
        if ($this->confirm('Are you sure want to flush all the keys?')) {
            Cache::store('redis')->flush();
            $this->info("✅ Flush all the keys successfully");
        }

        if ($this->confirm('Main options?')) {
            return $this->choiceOptions();
        }
    }

    /**
     * Get redis cache Keys  
     * 
     * @return \Illuminate\Support\Collection
     */
    private function getCacheKeys(): Collection
    {
        $cache_keys = Redis::connection('cache')->keys("*{$this->search_key}*");
        $cache_keys = collect($cache_keys)->map(fn ($value) => Str::replace($this->prefix, '', $value))->toArray();

        $keys_collection = collect($cache_keys)->map(fn ($value, $key) => [$key, Str::replace($this->prefix, '', $value)]);

        return $keys_collection;
    }
}
