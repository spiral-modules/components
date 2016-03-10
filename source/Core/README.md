Spiral IoC and Core classes
===========================
[![Latest Stable Version](https://poser.pugx.org/spiral/core/v/stable)](https://packagist.org/packages/spiral/core) 
[![License](https://poser.pugx.org/spiral/core/license)](https://packagist.org/packages/spiral/core) 
[![Build Status](https://travis-ci.org/spiral/core.svg?branch=master)](https://travis-ci.org/spiral/core)

<img src="https://raw.githubusercontent.com/spiral/guide/master/resources/logo.png" height="100px" alt="Spiral Framework" align="left"/>

Following repository container core interfaces and autowiring implementation of InteropContainer with
support of declarative singletons, delayed injectors and factory bindings.

[Website](https://spiral-framework.com) | [Guide / Documentation](https://spiral-framework.com/guide) | [Gitter](https://gitter.im/spiral/hotline) |   [**Contributing**](https://github.com/spiral/guide/blob/master/contributing.md)

<br/>

Examples of usage:
------------------

Cascade autowiring:

```php
$container = new Container();

assert($container->get(SomeClass::class) instanceof SomeClass);
```

Bindings:

```php
$container = new Container();

$container->bind(SomeInterface::class, SomeClass::class);
assert($container->get(SomeInterface::class) instanceof SomeClass);
```

Closure bindings:

```php
$container = new Container();

$container->bind('binding', function() {
    return new SomeClass();
});

assert($container->get('binding') instanceof SomeClass);
```

Factory Bindings:

```php
class MyFactory 
{
    public function makeClass($name)
    {
        return new SomeOtherClass($name);
    }
}

$container = new Container();

$container->bind(SomeClass::class, [MyFactory::class, 'makeClass']);
$someClass = $container->make(SomeClass::class, ['name' => 'some name']);

assert($someClass instanceof SomeOtherClass);
```

Singleton bindings:

```php
$container = new Container();
$container->bindSingleton('singleton', function() {
    return new SampleClass();
});

assert($container->get('singleton') === $container->get('singleton'));
```

Declarative singletons:

```php
class MySingleton implements SingletonInterface
{

}

$container = new Container();
assert($container->get(MySingleton::class) === $container->get(MySingleton::class));
```

and much more...

Documentation
-------------
Full dependency injection documentation in a context of Spiral Framework can be found [here](https://spiral-framework.com/guide/framework-container).

Check documentation about [Injectable Configs](https://spiral-framework.com/guide/framework-configs) and [Bootloaders](https://spiral-framework.com/guide/framework-bootloaders).
