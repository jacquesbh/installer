# The Installer

## What's that?

The "Installer" is a *simple* shell which allows you to create custom modules on Magento.

NOTE: This is the first version. Any idea for the second one?

## Howto

### Bash function required

    ```sh
    function jbh () {
        export USER_EMAIL="jacques@bodin-hullin.net"
        export USER_NAME="Jacques Bodin-Hullin"
        export DESIGN="base_default"
        export COMPANY_NAME="Jacques Bodin-Hullin"
        export COMPANY_NAME_SHORT="Jbh"
        export COMPANY_URL="http://jacques.sh/"
        export LOCALES="fr_FR,en_US"
        /usr/local/bin/php -f ~/.bin/Installer/Installer.php $@;
    }
    ```

### Run it!

With your shell, go under your magento dir, like that :

    $ cd ~/Sites/magento/

Then run the Installer! (with the function above)

    $ jbh
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
     | setup                | sql set               |                                           |
     | upgrade              | up                    | [from] to                                 |
     | event                |                       | name model method where                   |
     | default              | def conf              | name value                                |
     | depends              | dep                   | (-)module                                 |
     | exit                 |                       |                                           |
     | delete               | del rm remove         |                                           |
     | last                 |                       | [...]                                     |
     | addtranslate         | __                    |                                           |
     | routers              | r route router        | where frontName                           |
     | tmp                  |                       | action                                    |
     |                      |                       |                                           |
      ---------------------- ----------------------- -------------------------------------------
    > 

### Example

    $ jbh
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
    Jbh_Demo>

Easy, isn't it ?
