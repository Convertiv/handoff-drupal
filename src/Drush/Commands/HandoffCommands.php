<?php

namespace Drupal\handoff\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Serialization\Yaml;

use Drupal\Core\Utility\Token;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use Handlebars\Handlebars;

/**
 * A Drush commandfile.
 */
final class HandoffCommands extends DrushCommands
{

  use AutowireTrait;
  protected $name;
  protected $version = 'latest';
  public $handoff_config;
  public $command;
  public $client;
  public $base_url;
  public $theme;
  public $theme_path;
  public $theme_dir;
  public $component_dir;
  public $data;
  protected $force = false;
  protected $component;

  /**
   * Constructs a HandoffCommands object.
   */
  public function __construct(
    private readonly Token $token,
  ) {
    parent::__construct();
    $this->handoff_config = \Drupal::service('config.factory')->getEditable('handoff.settings');
  }

  /**
   * Command description here.
   */
  #[CLI\Command(name: 'handoff:fetch-component', aliases: ['handoff:fetch', 'fetch-component'])]
  #[CLI\Argument(name: 'name', description: 'Get the component name.')]
  #[CLI\Argument(name: 'version', description: 'Get the component version.')]
  #[CLI\Option(name: 'force', description: 'Force the operation and overwrite the results.')]
  #[CLI\Usage(name: 'handoff:fetch-component', description: 'Get a component from Handoff and save it as a single directory component.')]
  public function fetchComponent($name, $version = 'latest', $options = ['force' => false])
  {
    $this
      ->setName($name)
      ->setVersion($version)
      ->setForce($options['force'])
      ->askUrl()
      ->chooseTheme()
      ->fetch()
      ->validateComponent()
      ->exportComponent();

    $this->io()->text([
      'Component exported successfully.',
      'Component: ' . $this->component['title'],
      'Version: ' . $version,
      'You can use the component by including it in your twig file like so:',
      "",
      "{{ include('handoff_bootstrap:$name', { ",
      "  title_prefix: title[0], ",
      "  title: [title[1], title[2], title[3]],",
      "})}}",
    ]);
  }

  /**
   * Validate the component
   *
   * @return void
   */
  public function validateComponent()
  {
    $components_dir = $this->theme_dir . '/components';

    if (!is_dir($components_dir)) {
      mkdir($components_dir);
    }
    // Replace the name with the name from the actual component
    // TODO: Allow users to set a new name on import?
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $this->component['id']);
    $this->component_dir = $component_dir = $components_dir . '/' . $name;
    // TODO: If it doesn't exist, ask if we should create it
    // TODO: If it does exist, ask if we should overwrite it
    if (!is_dir($component_dir)) {
      mkdir($component_dir);
    } else {
      // if the component exists, lets parse the existing yaml file
      $config = Yaml::decode(file_get_contents($component_dir . "/$name.handoff.yaml"));
      if ($config['version'] === $this->version) {
        if (!$this->force) {
          $this->logger()->error('Component already exists and the version is unchanged');
          return;
        } else {
          $this->logger()->warning('Component already exists and the version is unchanged. Force option is enabled.');
        }
      } else {
        // Compare versions and ask if we should overwrite
        if ($config['version'] !== $this->version) {
          $this->logger()->warning('Component already exists and the version is different');
          $this->logger()->warning('Current Version: ' . $config['version']);
          $this->logger()->warning('New Version: ' . $this->version);
          $this->logger()->warning('Do you want to overwrite the component?');
          $overwrite = $this->io()->confirm('Overwrite Component?', FALSE);
          if (!$overwrite) {
            return;
          }
        }
      }
    }
    return $this;
  }
  /**
   * Ask the user for the Handoff Base Url
   *
   * @return \Drupal\handoff\Fetch
   */
  public function askUrl()
  {
    $config = $this->handoff_config;
    $url = $config->get('api_url');
    if ($url) {
      $this->io()->text("Using URL: $url");
    } else {
      $url = $this->io()->ask('Enter the URL handoff site you want to use: (e.g. https://handoff.example.com)');
      $config->set('api_url', $url);
      $config->save();
    }
    $this->base_url = $url;
    $this->client = new Client(['base_uri' => $url]);
    return $this;
  }

  /**
   * Choose theme
   *
   * @return Drupal\handoff\Drush\Commands\HandoffCommands
   */
  public function chooseTheme()
  {
    $config = $this->handoff_config;
    $theme = $config->get('theme');
    if ($theme) {
      $this->io()->text("Using theme: $theme");
      $theme_path = $config->get('theme_path');
    } else {
      // Check to see if we have a config
      $theme_handler = \Drupal::service('theme_handler');
      $installed_themes = $theme_handler->listInfo();
      $theme_choices = [];
      foreach ($installed_themes as $theme) {
        $theme_choices[$theme->getName()] = $theme->getPath();
      }
      $theme = $this->io()->choice('Select a theme', array_keys($theme_choices));
      $theme_path = $theme_choices[$theme];
      $config->set('theme', $theme);
      $config->set('theme_path', $theme_path);
      $config->save();
    }
    $this->theme = $theme;
    $this->theme_path = $theme_path;
    $this->theme_dir = DRUPAL_ROOT . DIRECTORY_SEPARATOR . $theme_path;
    return $this;
  }

  /**
   * Fetch the data from Handoff
   *
   * @return void
   */
  public function fetch()
  {
    try {
      $this->io()->text('Fetching component from Handoff...');
      if ($this->force) {
        $this->logger()->warning('Force option is enabled.');
      }
      // TODO: Make this url configurable
      $response = $this->client->get("/api/component/$this->name.json")->getBody()->getContents();
      $data = json_decode($response, TRUE);
    } catch (\Exception $e) {
      throw new \Exception("Could not find $this->name in library.");
    }
    // If a version is requested, lets see if it exists, otherwise use the latest
    $version = $this->version;
    if ($version !== 'latest') {
      if (isset($data[$version])) {
        $component = $data[$version];
      } else {
        if (!$this->force) {
          throw new \Exception("Could not find version $this->version in $this->name.");
        } else {
          $this->logger()->warning("Could not find version$this->version in $this->name. Force option is enabled.");
          $component = $data['latest'];
          $version = $data['version'];
        }
      }
    } else {
      if (isset($data['latest'])) {
        $component = $data['latest'];
        $version = $data['version'];
      } else {
        throw new \Exception("Could not find latest for $this->name.");
      }
    }
    $this->component = $component;
    $this->io()->text("Importing Component: $this->name");
    return $this;
  }

  /**
   * Export the component
   *
   * @return void
   */
  public function exportComponent()
  {
    $component = $this->component;
    $this->makeTwigFromComponent($this->component, $this->version);
    $this->makeYamlFromComponent();
    $this->getSharedCSS();
    // Write the component css to the component directory
    file_put_contents($this->component_dir . "/$this->name.css", $component['css']);
    // Write the component js to the component directory
    file_put_contents($this->component_dir . "/$this->name.js", $component['js']);
    // Write the component json to the component directory
    // TODO: This should 
    file_put_contents($this->component_dir . "/$this->name.handoff.yaml", Yaml::encode([
      'name' => $component['title'],
      'description' => $component['description'],
      'version' => $this->version,
      'properties' => $component['properties'],
      // TODO: This file should describe the slot mapping as well
      // And should be used to handle upgrades in the fture
    ], JSON_PRETTY_PRINT));
    return $this;
  }

  /**
   * Make a twig template from a component
   *
   * @param [type] $component
   * @param [type] $version
   * @return void
   */
  public function makeTwigFromComponent($component, $version)
  {
    $twig = "{# @file\n  * This is a component template for the {$component['title']} component\n";
    $twig .= "  * @see https://stage-ssc.handoff.com/api/component/{$component['id']}\n";
    $twig .= "  * @version $version\n";
    $twig .= "  * @date " . date('Y-m-d') . "\n";
    $twig .= "  * @author Handoff\n";
    $twig .= "  * #}\n";
    // TODO: Convert the handlebars code into a twig template
    // and inject the properties\
    $handlebars = new Handlebars();
    // Transform the handlebars code into twig
    // First we need to find special cases like {{#each title}} and replace them with a for loop
    $code = preg_replace_callback('/{{#each([\s\S]*?)}}([\s\S]*?){{([\s\S]*?)}}([\s\S]*?){{\/each}}/', function($match) {
      $loop = str_replace('properties.', '', $match[1]);
      $loop_short = $loop[0];
      dd($match);
      return "{% for $loop_short in $loop %} $2 {{}} $4 {% endfor %}";
    }, $component['code']);
    dd($code);
    // handle if case
    $code = preg_replace_callback('/{{#if([\s\S]*?)}}([\s\S]*?){{\/if}}/', function ($matches) {
      $if = str_replace('properties.', '', $matches[1]);
      return "{% if $if %} $matches[2] {% endif %}";
    }, $code);
    // TODO: handle other special cases (if, else, etc)
    // build properties list 
    $props_array = [
      'properties' => [
        'title_loop' => 't',
        'title_name' => 'title',
        'title_var' => '{{ t }}',
      ]
    ];
    foreach ($component['properties'] as $key => $property) {
      $props_array['properties'][$key] = "{{ $key }}";
    }
    $twig .= $handlebars->render($code, $props_array);
    // Write the component twig to the component directory
    file_put_contents($this->component_dir . "/$this->name.twig", $twig);
    return $this;
  }

  /**
   * Make a yaml file from a component
   *
   * @param [type] $component
   * @return void
   */
  public function makeYamlFromComponent()
  {
    $component = $this->component;
    $props = [
      'type' => 'object',
      'required' => [],
      'properties' => [],
    ];
    // TODO: Allow the user to map the handoff properties to drupal props
    foreach ($component['properties'] as $key => $property) {
      $props['properties'][$key] = [
        'type' => $property['type'],
        'title' => $property['name'],
        'description' => isset($property['description']) ? $property['description'] : '',
      ];
    }
    $yaml = Yaml::encode([
      'name' => $component['title'],
      // TODO: Allow the config to define the status, and allow a user to set the status on import
      'status' => 'experimental',
      'group' => 'handoff', // TODO: Allow the user to set the group on import
      'properties' => $props,
    ]);
    // Write the component yaml to the component directory
    file_put_contents($this->component_dir . "/$this->name.component.yml", $yaml);
  }

  /**
   * Get the shared CSS from Handoff and save it to the theme
   *
   * @return void
   */
  public function getSharedCSS()
  {
    // check to see if the components dir exists
    // if not, create it
    // TODO: Users should be able to save a configuration so that we don't have to ask things in future
    // TODO: allow users to set where they want to save the components
    // Get css dir
    $css_dir = $this->theme_dir . '/css';
    // TODO: check to see if the css dir exists, and if the handoff_main already exists
    // TODO: Ask if we should overwrite, and check versioning

    try {
      $response = $this->client->request('GET', "/api/component/shared.css")->getBody()->getContents();
      file_put_contents($css_dir . "/handoff_main.css", $response);
    } catch (\Exception $e) {
      throw new \Exception('Could not find component in library. | ' . $e->getMessage());
    }
    return $this;
  }

  /**
   * Set the name property
   */
  public function setName($name)
  {
    $this->name = $name;
    return $this;
  }

  /**
   * Set the version property
   */
  public function setVersion($version)
  {
    $this->version = $version;
    return $this;
  }

  /**
   * Set the force property
   */
  public function setForce($force)
  {
    $this->force = $force;
    return $this;
  }
}