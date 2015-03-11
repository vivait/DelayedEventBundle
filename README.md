Delayed Event Bundle
===============

# Configure the service
```yaml
vivait_delayed_event:
    queue_transport: beanstalkd # default
    serializer: doctrineorm # default
```

## Beanstalkd queue transport
This relies on [pheanstalk](https://github.com/armetiz/LeezyPheanstalkBundle/blob/master/Resources/doc/1-installation.md) 
being installed and setup in your config.

# Creating a delayed event
Instead of tagging the event with a kernel tag, tag the event with a `delayed_event` tag and provide a delay:

```yaml
# app/config/services.yml
services:
    app.your_listener_name:
        class: AppBundle\EventListener\AcmeListener
        tags:
            - { name: delayed_event.event_listener, delay: '24 hours', event: app.my_event, method: onMyEvent }
```

By default, any integer will be treated as seconds. The bundle will use [PHP's textual datetime parsing](http://php.net/manual/en/function.strtotime.php)
to parse a textual datetime string in to seconds like in the example above.
