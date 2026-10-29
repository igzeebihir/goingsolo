/**
 * @file
 * Asks AdSense to fill each ad slot once.
 */
((Drupal, once) => {
  Drupal.behaviors.goingsoloAdSlot = {
    attach(context) {
      once('gs-ad', 'ins.adsbygoogle', context).forEach(() => {
        try {
          (window.adsbygoogle = window.adsbygoogle || []).push({});
        }
        catch (e) {
          // AdSense not loaded, for example blocked by the visitor.
        }
      });
    },
  };
})(Drupal, once);
