# The Installer

The Installer is under the General Public License. See the `COPYING` for further information.

## What's that?

The "Installer" is a *simple* shell which allows you to create custom modules on Magento.

### V2

The second version will be developped with the Console component of Symfony. If you want help us to build it, let me know! <jacques ON bodin-hullin DOT net>

## Install

### On UNIX

Puppet file: <https://github.com/monsieurbiz/vagrant-magento/blob/master/puppet/modules/tools/manifests/installer.pp>

    # step by step
    git clone git://github.com/jacquesbh/installer.git /usr/local/src/installer
    ln -s /usr/local/src/installer/bin/Installer /usr/local/bin/installer
    
Don't forget to pull sometimes ;)

    cd /usr/local/src/Installer && git pull

### On Windows

... Sorry dude ;)

## Howto

### Configure

Each time I need to use The Installer, I've a git repository.  
Because it's always a Magento project and I always use git as SCM.

```sh
git config jbh-installer.company-name "The Company"
git config jbh-installer.company-name-short "company"
git config jbh-installer.user-name "John Doe"
git config jbh-installer.user-email "john.doe@example.org"
git config jbh-installer.company-url "http://example.org"
```

You can use the following configuration path:

*   `path`: The path to the "app" directory. (optional)
*   `company-name`: The name of your company.
*   `company-name-short`: The namespace of your company.
*   `company-url`: The website url of your company.
*   `user-name`: Your name.
*   `user-email`: Your email.
*   `license`: The license used. "All rights reserved" as default.
*   `design`: The project's design. "base_default" as default.
*   `locales`: The project's locales. "fr_FR,en_US" as default.

In the lastest version the Installer will ask you those information.

### Binary

You can use the binary `bin/Installer`.

Just create a simple symlink link.

### Run it!

With your shell, go under your magento dir, like that :

    $ cd ~/Sites/magento/

Then run the Installer! (with the function above)

    $ installer
    The Installer - by jacquesbh
    > 

### Help

Type `?`!

    > ?
      ---------------------- ----------------------- -------------------------------------------
     | COMMAND              | ALIAS                 | PARAMETERS                                |
     |----------------------|-----------------------|-------------------------------------------|
     | help                 | -h ?                  |                                           |
     | module               | mod                   | namespace name pool                       |
     | general              |                       |                                           |
     | info                 | i config conf         |                                           |
     | clean                |                       | [all, cache, log(s)]                      |
     | controller           | c                     | name [actions]                            |
     | helper               | h                     | name [methods]                            |
     | model                | m                     | name [methods]                            |
     | observer             | o                     | name [methods]                            |
     | observer Observer    | oo                    | [methods]                                 |
     | block                | b                     | name [methods] [-p]                       |
     | translate            | t                     | where                                     |
     | translates           | ts                    |                                           |
     | layout               | l                     | where                                     |
     | layouts              | ls                    |                                           |
     | resources            | res                   |                                           |
     | entity               | ent                   | name table                                |
     | grid                 |                       | entity                                    |
     | form                 |                       | entity                                    |
     |----------------------|-----------------------|-------------------------------------------|
     | COMMAND              | ALIAS                 | PARAMETERS                                |
     |----------------------|-----------------------|-------------------------------------------|
     | setup                | sql set               |                                           |
     | data                 |                       | [[from] to]                               |
     | upgrade              | up                    | [from] to                                 |
     | event                |                       | name model method where                   |
     | cron                 |                       | identifier 1 2 3 4 5 model method         |
     | default              | def conf              | name value                                |
     | depends              | dep                   | (-)module                                 |
     | exit                 |                       |                                           |
     | delete               | del rm remove         |                                           |
     | last                 |                       | [...]                                     |
     | addtranslate         | __                    |                                           |
     | routers              | r route router        | where frontName                           |
     | tmp                  |                       | action                                    |
     | misc                 | script                | name (without .php)                       |
     | doc                  |                       | [title]                                   |
     | system               |                       |                                           |
     | adminhtml            |                       |                                           |
     | session              |                       | [methods]                                 |
     | email                | mail                  | name                                      |
     |                      |                       |                                           |
      ---------------------- ----------------------- -------------------------------------------
    > 

### Example

    $ installer
    The Installer - by jacquesbh
    > mod jbh demo local
    Using: Jbh_Demo in local
    Jbh_Demo> ent
    Entity?
    > foo
    Table?
    > jbh_foo
    Jbh_Demo> h data -
    Jbh_Demo> layout
    Where? (enter for front)
    > 
    Jbh_Demo> session
    Jbh_Demo>

Easy, isn't it ?

### Tips

#### Variables?

    Jbh_Tmp> model foo $_iAmProtected $_meToo=1 $iAmPublic $meToo=array $iAmNull=null $iAmAString=foo $_iAmBoolean=false

```php
<?php
/**
[...]
 */

/**
 * Foo Model
 * @package Jbh_Tmp
 */
class Jbh_Tmp_Model_Foo extends Mage_Core_Model_Abstract
{

// Company Tag NEW_CONST

    /**
     * short_description_here
     * @var 
     */
    protected $_iAmProtected = null;

    /**
     * short_description_here
     * @var int
     */
    protected $_meToo = 1;

    /**
     * short_description_here
     * @var 
     */
    public $iAmPublic = null;

    /**
     * short_description_here
     * @var array
     */
    public $meToo = array();

    /**
     * short_description_here
     * @var null
     */
    public $iAmNull = null;

    /**
     * short_description_here
     * @var string
     */
    public $iAmAString = 'foo';

    /**
     * short_description_here
     * @var bool
     */
    protected $_iAmBoolean = false;

// Company Tag NEW_VAR

// Company Tag NEW_METHOD

}
```

#### Constants?

    Jbh_Tmp> model foo I_AM_CONSTANT I_AM_A_STRING=foo I_AM_INTEGER=123 I_AM_BOOLEAN=true

```php
<?php
/**
[...]
 */

/**
 * Foo Model
 * @package Jbh_Tmp
 */
class Jbh_Tmp_Model_Foo extends Mage_Core_Model_Abstract
{

    /**
     * short_description_here
     * @const I_AM_CONSTANT
     */
    const I_AM_CONSTANT = null;

    /**
     * short_description_here
     * @const I_AM_A_STRING string
     */
    const I_AM_A_STRING = 'foo';

    /**
     * short_description_here
     * @const I_AM_INTEGER int
     */
    const I_AM_INTEGER = 123;

    /**
     * short_description_here
     * @const I_AM_BOOLEAN bool
     */
    const I_AM_BOOLEAN = true;

// Company Tag NEW_CONST

// Company Tag NEW_VAR

// Company Tag NEW_METHOD

}
```

#### Methods?

    Jbh_Tmp> model foo _iAmProtected iAmPublic iReturnsBool:bool iReturnsObject:Varien_Object iCallMyParent/p iHaveArgs() iHaveArgsAndIReturnsBool():bool _iHaveArgsIReturnsStringICallDad():string/p
    Return for iCallMyParent()?
    > string
    Params for iHaveArgs()?
    > $customer
    Params for iHaveArgsAndIReturnsBool()?
    > $flag
    Params for iHaveArgsIReturnsStringICallDad()?
    > $order

```php
<?php
/**
[...]
 */

/**
 * Foo Model
 * @package Jbh_Tmp
 */
class Jbh_Tmp_Model_Foo extends Mage_Core_Model_Abstract
{

// Company Tag NEW_CONST

// Company Tag NEW_VAR

    /**
     * short_description_here
     * @return 
     */
    protected function _iAmProtected()
    {
        // Code here
    }

    /**
     * short_description_here
     * @return 
     */
    public function iAmPublic()
    {
        // Code here
    }

    /**
     * short_description_here
     * @return bool
     */
    public function iReturnsBool()
    {
        // Code here
    }

    /**
     * short_description_here
     * @return Varien_Object
     */
    public function iReturnsObject()
    {
        // Code here
    }

    /**
     * short_description_here
     * @return string
     */
    public function iCallMyParent()
    {
        parent::iCallMyParent();
        // Code here
    }

    /**
     * short_description_here
     * @return 
     */
    public function iHaveArgs($customer)
    {
        // Code here
    }

    /**
     * short_description_here
     * @return bool
     */
    public function iHaveArgsAndIReturnsBool($flag)
    {
        // Code here
    }

    /**
     * short_description_here
     * @return string
     */
    protected function _iHaveArgsIReturnsStringICallDad($order)
    {
        parent::_iHaveArgsIReturnsStringICallDad($order);
        // Code here
    }

// Company Tag NEW_METHOD

}
```

## Documentation

If you want generate a documentation for your modules, you can do it! And the Installer is helpful!

    > doc Title if you want :)

This command creates a simple directory named `doc` with a file `README.md`.

The idea is to use _Easybook_ for generate the documentation in PDF or website formats.

## system.xml & adminhtml.xml

You can generate those files but you need to edit them after.
It's customized samples.

