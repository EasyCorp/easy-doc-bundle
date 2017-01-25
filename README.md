EasyDoc
=======

This bundle generates the formal documentation for Symfony applications. This
documentation is a HTML document that contains detailed information about every
application element (routes, services, events, configuration, etc.)

When would this bundle be useful?

  * As a reference to look for any application element details.
  * As a document to get an overall idea of the application when adding new
    developers to the project.
  * As a deliverable to the client who paid for the application development.
  * As a *searchable* archive of legacy applications.

This is how it looks:

![EasyDoc in action](/src/Resources/doc/images/easydoc-index.png)

Installation
------------

### Step 1: Download the Bundle

```bash
$ composer require --dev easycorp/easy-doc-bundle
```

### Step 2: Enable the Bundle

```php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        // ...

        if (in_array($this->getEnvironment(), ['dev', 'test'])) {
            // ...

            if ('dev' === $this->getEnvironment()) {
                // ...
                $bundles[] = new EasyCorp\Bundle\EasyDocBundle\EasyDocBundle();
            }
        }
    }

    // ...
}
```

Usage
-----

Run the `doc` command in your Symfony application to generate the documentation:

```bash
$ cd your-project/
$ ./bin/console doc
```
