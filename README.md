Delayed Event Bundle
===============
Triggers a Symfony event an arbitrary period after the original event

# Which version to use
1. For Symfony 3 use version 1.x
2. For Symfony 4+ use version 2.x

# Configure the service
```yaml
vivait_delayed_event:
    queue_transport: beanstalkd # default
```

## Beanstalkd queue transport
This relies on [pheanstalk](https://github.com/armetiz/LeezyPheanstalkBundle/blob/master/Resources/doc/1-installation.md) 
being installed and setup in your config. You can pass extra information to the beanstalk queue using the `configuration` parameter:

Be aware TTR is the time a process can run before it effectively retries, if it's too short there is a realistic
possibility that a job will be processed twice.
```yaml
vivait_delayed_event:
    queue_transport:
        name: beanstalkd
        configuration:
            tube: my_tube
            ttr: 60
```

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
    /**
* @param ReflectionProperty $property
 * @return bool
*/public function supports(\ReflectionProperty $property) {
        $property->getValue();
        return is_object($data) && $this->doctrine->contains($data);
    }

    /**
* @param $data
 * @return array
*/public function serialize($data)
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

    /**
* @param $data
 * @return mixed
*/public function deserialize($data)
    {
        [$class, $id] = $data;
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
