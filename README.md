Delayed Event Bundle
===============
Triggers a Symfony event an arbitrary period after the original event

# Configure the service
```yaml
vivait_delayed_event:
    queue_transport: beanstalkd # default
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

# Normalizers
If you're delaying an event, rather than store the exact state of an entity at the time of the event, you'll probably
want to receive the latest version of the entity. The bundle allows the usage of normalizers, which are ran before
each property of an event is serialized. By default, the bundle has an entity normalizer enabled, which will detect
any entities in an event and store a reference to an entity. This means that when the entity is unserialized for the
delayed event, a fresh entity is loaded from the database.

You can create custom normalizers by implementing the `NormalizerInterface` and `DenormalizerInterface` interfaces:

You must then tag the custom normalizer:
```yaml
# app/config/services.yml
services:
    app.your_normalizer_name:
        class: AppBundle\EventTransformer\AcmeNormalizer
        tags:
            - { name: delayed_event.normalizer }
```
