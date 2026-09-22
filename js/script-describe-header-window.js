/**
 * @file
 * Behaviors specific to describe header window.
 *
 * @see templates/describe-header-window.html.twig
 * @see css/style-validation-result-window.css
 */

(function (Drupal, once) {

  Drupal.behaviors.describeHeaderWindow = {
    attach(context, settings) {

      // Add expand or collapse behavior to the column window.
      once('describe-header-window', '#tcp-resize-window', context)
        .forEach((element) => {

          element.addEventListener('click', (event) => {
            event.preventDefault();
            // The container element housing the columns.
            const traitWindow = document.getElementById('tcp-expandable-window');

            if (!traitWindow) {
              return;
            }

            // Toggle window expanded/collapsed state.
            element.textContent = traitWindow.classList
              .toggle('tcp-window-max') ? 'Collapse' : 'Expand';
          });

        });
      }

    };
})(Drupal, once);
