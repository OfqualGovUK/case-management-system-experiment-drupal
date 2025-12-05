# Carbon Design System (web components for Drupal)

A custom theme to bring in required Carbon Design System Web Components.

It should NOT be use for project specific styles or overwrites, instead that should come from a child theme.

## Getting started
To build the theme simple run two tasks

```
npm install
npm run build
```
## Theme structure
All Carbon Design System live in the components folder, and use the standard SDC approach for Drupal for each component.

Each component is defined from components/{{ name }} and requires a {{ name }}.component.yml file that defines all the info for the component, a {{ name }}.twig for the markup and a js file from src/{{ name }}.js which should be the cds es raw file.

The rest of the theme structure follows normal Drupal theme approaches, templates inside the template divided into sub folders by the base / core module it uses.

### Forms
Form elements are deliberately ignored from the web components approach due to various challenges with getting the correct markup passed to the form.
