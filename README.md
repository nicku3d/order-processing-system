# Order processing system

Example of order processing system using RabbitMQ.

Project created with https://github.com/dunglas/symfony-docker template.
 
[How to use docker](docker.md)


### Commands

Run queue worker for 1 minute inside of php container:

```
php bin/console messenger:consume amqp --time-limit=60 --memory-limit=128M
```

