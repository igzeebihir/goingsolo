/**
 * @file
 * Mobile navigation toggle.
 */
((Drupal, once) => {
  Drupal.behaviors.goingsoloNav = {
    attach(context) {
      once('gs-nav', '[data-gs-nav-toggle]', context).forEach((button) => {
        const nav = document.getElementById(button.getAttribute('aria-controls'));
        button.addEventListener('click', () => {
          const open = button.getAttribute('aria-expanded') !== 'true';
          button.setAttribute('aria-expanded', String(open));
          nav.classList.toggle('is-open', open);
          document.body.classList.toggle('gs-nav-open', open);
        });
      });
    },
  };
})(Drupal, once);
