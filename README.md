## Handoff Components

This module is a drupal developer tool designed to integrate your Drupal theme
with the Handoff component library API

The commands provided by this library allow you to fetch components down and
attach them to a theme in the project

## INSTALLATION

This package has not yet been published to drupal or composer. You should install
it manually for now

1. Download this package
2. Copy it into a place modules can be installed eg. web/modules/handoff
3. Install it via drush or the module page
4. Run composer install in your drupal root to ensure dependencies are loaded

## USAGE

This script is going to attempt to pull a component down from the handoff API
and transpile it to a Drupal single directory component. You'll need to provide
a couple of pieces of data

1. The url of your handoff site, either locally running or on the internet.
2. The theme you wish to add the components to. The command will give you a list
of themes to choose from.  
3. The name of the component you want to import. If you don't supply it, the 
command will list all the components in the library and allow you to choose.
4. Optionally the version you wish to pull. If you don't supply it, we'll assume
you'd like the latest.

Use drush as follows - 

### To Fetch a component
`drush handoff:fetch-component {component_id} {version?} --force?`

This will pull a component, transpile it and install it in your theme at
{theme}/components/{id}



## MAINTAINERS

Current maintainers for Drupal 10:

- Brad Mering (brad@convertiv.com) - https://www.drupal.org/u/NICKNAME

