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

## Storing the event details
This bundle utilises the [Doctrine Cache Bundle](https://github.com/doctrine/DoctrineCacheBundle) as a storage facility
for the serialized event. This means you'll need to setup a provider for delayed event bundle to use as its `storage`
parameter, like below:

```yaml
doctrine_cache:
    providers:
        delayed_event_cache:
            sqlite3:
                file_name: '%kernel.cache_dir%/delayed_event_cache.db'

vivait_delayed_event:
    storage: delayed_event_cache
```

Make sure the life of the cache is atleast as long as the life of your queue and accessible by any workers - i.e. if 
your worker is on a different system than the cache provider, make sure it is accessible. Likewise make sure it's going
to atleast live as long as your queue.

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

# Transformers
If you're delaying an event, rather than store the exact state of an entity at the time of the event, you'll probably
want to receive the latest version of the entity. The bundle allows the usage of transformers, which are ran before
each property of an event is serialized. By default, the bundle has an entity transformer enabled, which will detect
any entities in an event and store a reference to an entity. This means that when the entity is unserialized for the
delayed event, a fresh entity is loaded from the database.

You can enable/disable transformers on a global level:

```yaml
# app/config.yml
vivait_delayed_event:
    storage: delayed_event_cache
    transformers:
        doctrine: disabled
```


You can also enable/disable them when tagging an event listener:
```yaml
# app/config/services.yml
services:
    app.your_listener_name:
        class: AppBundle\EventListener\AcmeListener
        tags:
            - {
                name: delayed_event.event_listener, delay: '24 hours', event: app.my_event, method: onMyEvent,
                transformers: [doctrine]
            }
```

*Note:* The `enabled` part is optional, and in the example above has been left out for brevity.

You can create custom transformers by implementing the `TransformerInterface` interface, like so:

```php
class AcmeTransformer implements TransformerInterface
{
    public function supports(\ReflectionProperty $property) {
        $property->getValue();
        return is_object($data) && $this->doctrine->contains($data);
    }

    public function serialize($data)
    {
        // Get the ID
        $id = $this->doctrine->getMetaData($data)->getIdentifierFieldNames();

        $class = get_class($data);

        /* @var UnitOfWork $uow */
        $uow = $this->doctrine->getUnitOfWork();
        $id = $uow->getDocumentIdentifier($data);

        return [
            $class,
            $id
        ];
    }

    public function deserialize($data)
    {
        list($class, $id) = $data;
        return $this->doctrine->getRepository($class)->find($id);
    }
}

```

You must then tag the custom transformer:
```yaml
# app/config/services.yml
services:
    app.your_transformer_name:
        class: AppBundle\EventTransformer\AcmeTransformer
        tags:
            - { name: your_transformer_name }
```
