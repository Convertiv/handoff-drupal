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


This will pull a component, transpile it and install two pieces into your theme.

First it will pull the shared styles and install them in a file at 
`css/handoff_main.css` in your theme. You should update your theme.info.yml file
to serve these base styles.

Second, it will pull and transpile the component itself. This will generate 5
files - 
- {id}.twig - The component template file
- {id}.scss - The component style file. This will automatically get loaded if
the component is used in a template
- {id}.js - The component script file. This will automatically get loaded if the 
component is used in a template
- {id}.component.yml - The component definition file. This contains the 
component name and any variables that can be passed to it
- {id}.handoff.json - This contains the metadata about the component. This is 
used to determine if the component has changed upstream, and acts as a lock file 
for the component.


## Next steps
- Version the shared styles and check when fetching if upstreams styles have changed
- Improve the twig transpiler to handle handlebar partials
- Generate use documentation when the script fetches the component
- Build diff comparisons when upgrading a component
- Document limitations in handlebar ingest
- Build out documentation and mapping examples of how you can use this
- Clear the theme cache optionally when importing or updating a component

## MAINTAINERS

Current maintainers for Drupal 10:

- Brad Mering (brad@convertiv.com) - https://www.drupal.org/u/NICKNAME

