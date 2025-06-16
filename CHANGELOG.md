# CHANGELONG

## v2.*
- From v2 upwards, this package executes workflow tasks asynchronously and in parallel
- All rules and tasks in the workflow are treated as a Directed Acyclic Graph (DAG) which is handled handled by the [PHP DAG](https://github.com/jumaphelix/php-dag)  package.
- You must install [Swoole](https://github.com/swoole/swoole-src) which is what is used to manage parallel task execution.
