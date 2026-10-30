/**
 * @file
 * Stands in for Olivero's navigation-utils.js.
 *
 * The theme has its own header, but Olivero scripts such as messages.js still
 * expect Drupal.olivero to exist.
 */
((Drupal) => {
  Drupal.olivero = Drupal.olivero || {};
  Drupal.olivero.isDesktopNav = () => window.matchMedia('(min-width: 78rem)').matches;
  Drupal.olivero.stickyHeaderState = () => false;
  Drupal.olivero.areAnySubNavsOpen = () => false;
  Drupal.olivero.closeAllSubNav = () => {};
  Drupal.olivero.toggleSubNav = () => {};
  Drupal.olivero.searchIsVisible = () => false;
  Drupal.olivero.toggleSearchVisibility = () => {};
})(Drupal);
