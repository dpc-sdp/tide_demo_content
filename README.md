# tide_demo_content
Provides demo content for Tide modules.

# CONTENTS OF THIS FILE
* Introduction
* Requirements
* Usage
* For developers

# INTRODUCTION
The Tide Demo Content module leverages the Yaml Content module to provide 
default demo content for the Tide modules. It also enables privileged users
to import YAML content via Admin UI. 

# REQUIREMENTS
* [Yaml Content](https://drupal.org/project/yaml_content)

# USAGE
* The Tide Demo Content imports all demo content upon installation.
* It also removes all imported demo content when it is uninstalled.
* Privileged users with the "manually import demo content" permission can
import YAML content file via the UI: Admin > Content > Import Demo Content.
* To construct the YAML content file, please refer to the following resources:
  * [Yaml Content module](https://drupal.org/project/yaml_content)
  * [Creating Content with YAML Content Module](https://www.mediacurrent.com/blog/creating-content-yaml-content-module/)
    
# FOR DEVELOPERS
The Tide Demo Content module utilises the Yaml Content module to import its
own definition of demo content.
* Drush commands of the [Yaml Content module](https://drupal.org/project/yaml_content)
module will not recognise demo content defined under the structure of Tide
Demo Content, hence they will not import those demo content.
* The Tide Demo Content module will not remove standard YAML content imported by 
Drush commands of YAML Content module.
* Custom demo content can be defined in the `yourmodule.tide_demo_content.yml`.
Each demo content collection can have the following keys:
  * `dependencies` declares the requirements of the collection:
    1. `modules`: list of required modules. If at least one required module is 
    not enabled, the collection will be ignored by Tide Demo Content.
    2. `collections`: list of demo content collection to be imported before
    this collection can be imported. If a required collection is missing, this
    collection will be ignored.
  * `content` declares the list of `.content.yml` files of the collection.
    * Tide Demo Content module will look for `.content.yml` files under the 
    directory `demo_content/content/` of your module to import.
    * If the `content` key has a directory, everything under that directory
    will be imported recursively.
    * All items of the `content` keys will be imported in the same order defined
    in the collection.
    * Images and files will be imported from the `demo_content/images` and
    `demo_content/data_files` directories.

## Example
The structure of `mymodule`:
````
 |- demo_content
 |  |- content
 |     |- taxonomy_term
 |     |  |- mymodule.tags.yml
 |     |  |- mymodule.topic.yml
 |     |- mymodule.extra.content
 |     |- mymodule.media.content.yml
 |     |- mymodule.node.content.yml  
 |- mymodule.info.yml
 |- mymodule.install
 |- mymodule.module
 |- mymodule.tide_demo_content.yml   
````
`mymodule.tide_demo_content.yml`
````yaml
mymodule.demo:
  dependencies:
    modules:
      - mymodule
      - tide_core
    collections:
      - tide_demo_content:tide_core.demo
      - tide_demo_content:tide_site.demo
  content:
    - taxonomy_term
    - mymodule.media.content.yml
    - mymodule.node.content.yml
mymodule.extra_demo:
  dependencies:
    modules:
      - mymodule
      - tide_core
    collections:
      - mymodule:mymodule.demo
  content:
    - mymodule.extra.content.yml
````    
The `mymodule` modules defines 2 demo content collections:
1. `mymodule.demo` 
    * requires 2 modules: mymodule and tide_core.
    * depends on 2 demo collections `tide_core.demo` and `tide_site.demo` from
    the `tide_demo_content` module.
    * Tide Demo Content will import the `.content.yml` files of `mymodule.demo`
    in the following order:
      1. taxonomy_term/*
      2. mymodule.media.content.yml
      3. mymodule.node.content.yml    
2. `mymodule.extra_demo`
    * requires 2 modules: mymodule and tide_core.
    * depends on and will be imported after `mymodule.demo`

## Custom Yaml Content process plugins
* **URI Reference**: derived from the *Reference* process plugin. It accepts all
parameters of the `reference` process plugin and returns the `uri` of the found
entity instead of the ID. This plugin can be used for Link fields or Menu items.
````yaml
  field_external_link:
    - uri: https://www.vic.gov.au
      title: Victorian Government
  field_internal_link:
    - '#process':
        callback: uri_reference
        args:
          - node
          - type: page
            title: Demo Page 
      title: 'Read more'  
````    

## Hooks
* `hook_tide_demo_content_entity_imported` is invoked after Tide Demo Content
imports a demo entity.

## Known issues
* Yaml Content: [Nodes Cannot Create Menu Links Automatically](https://www.drupal.org/node/2879468)
* Yaml Content: [Nodes Cannot Create Path Aliases](https://www.drupal.org/project/yaml_content/issues/2883434)
* Tide Demo Content: the Import Demo Content UI only allows to import YAML
content, it does not allow to upload files/images. Hence, entities in the 
uploaded YAML can only reference existing files/images.
