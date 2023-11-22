# OSHITSD/command
This is command line interface for showing redis cache key, remove, flush all the keys and search. DB helper is the same process for showing table list, column list, drop table etc.

## License

Released under [MIT](/LICENSE) by [@oshit-sd](https://github.com/oshit-sd).

# Instruction
Just copy this file and put it in your application.


```php
// download this files and put it in this folder location
your_application\app\Console\Commands

example: 
    your_application\app\Console\Commands\DatabaseHelper.php
    your_application\app\Console\Commands\RedisCacheKey.php
```

```bash
Let's start with your terminal and run this command, give an example below.

suppose you can see the Redis lists, just run `php artisan redis:cache` then enter 0 
```

## redis command
```bash
    php artisan redis:cache

    Main options:
    [0] lists // all keys list
    [1] search // search specific key
    [2] keys // for delete specific key 
    [3] flush // delete all the keys
    [4] quit // command exit
    > 0
```

## database command
```bash
    php artisan db:helper

    Main options or [quit]:
    [0] tables // all the table lists
    [1] table-columns
    [2] truncate-table
    [3] drop-table
    [4] add-column
    [5] drop-column
    [6] change-column
    [7] fresh-table // fresh all the tables
    [8] quit
    > 0
```
