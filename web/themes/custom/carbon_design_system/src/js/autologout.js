/**
 * @file
 * JavaScript for autologout using Carbon Design System Modal.
 */

(function ($, Drupal, cookies) {

  'use strict';

  /**
   * Used to lower the cpu burden for activity tracking on browser events.
   *
   * @param {function} f
   * The function to debounce.
   */
  function debounce(f) {
    let timeout;
    return function () {
      let savedContext = this;
      let savedArguments = arguments;
      let finalRun = function () {
        timeout = null;
        f.apply(savedContext, savedArguments);
      };
      if (!timeout) {
        f.apply(savedContext, savedArguments);
      }
      clearTimeout(timeout);
      timeout = setTimeout(finalRun, 500);
    };
  }

  /**
   * Helper function to set a session cookie with required security flags.
   * Session cookie: OMITTING the 'expires' attribute.
   *
   * @param {string} cname
   * The cookie name.
   * @param {string} cvalue
   * The cookie value.
   */
  function setSessionCookie(cname, cvalue) {
    // The Secure flag is mandatory when using SameSite=Strict on modern browsers (requires HTTPS).
    document.cookie = cname + "=" + cvalue + ";path=/;SameSite=Strict;Secure";
  }

  /**
   * Wait for custom element to be defined and ready
   */
  function waitForCustomElement(tagName, timeout = 10000) {
    return new Promise((resolve, reject) => {
      // If already defined, wait a bit longer for full initialization
      if (customElements.get(tagName)) {
        setTimeout(() => resolve(), 500);
        return;
      }

      // Otherwise wait for it to be defined
      const timeoutId = setTimeout(() => {
        reject(new Error(`Timeout waiting for ${tagName}`));
      }, timeout);

      customElements.whenDefined(tagName).then(() => {
        clearTimeout(timeoutId);
        // Give it extra time to fully initialize
        setTimeout(() => resolve(), 500);
      }).catch(reject);
    });
  }

  /**
   * Attaches the batch behavior for autologout.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.autologout = {
    attach: function (context, settings) {
      // ADDED: Prevent the behavior from running more than once.
      if (settings.autologout_initialized) {
        return;
      }

      settings.autologout_initialized = true;

      if (context !== document) {
        return;
      }

      let paddingTimer;
      let theModal;
      let t;
      let localSettings;
      let initTimer;

      // Timer to keep track of activity resets.
      let activityResetTimer;

      // Prevent settings being overridden by ajax callbacks by cloning it.
      localSettings = jQuery.extend(true, {}, settings.autologout);

      const cookieName = "Drupal.visitor.autologout_login";
      if (!cookies.get(cookieName)) {
        setSessionCookie(cookieName, Math.round((new Date()).getTime() / 1000));
      }

      if (localSettings.refresh_only) {
        // On pages where user shouldn't be logged out, don't set the timer.
        t = setTimeout(keepAlive, localSettings.timeout);
      }
      else {
        settings.activity = false;
        if (localSettings.logout_regardless_of_activity) {
          // Ignore users activity and set timeout.
          let timestamp = Math.round((new Date()).getTime() / 1000);
          let login_time = cookies.get(cookieName);
          let difference = (timestamp - login_time) * 1000;

          t = setTimeout(init, localSettings.timeout - difference);
        }
        else {
          // Bind formUpdated events to preventAutoLogout event.
          $('body').bind('formUpdated', debounce(function (event) {
            $(event.target).trigger('preventAutologout');
          }));

          // Bind formUpdated events to preventAutoLogout event.
          $('body').bind('mousemove', debounce(function (event) {
            $(event.target).trigger('preventAutologout');
          }));

          // Replaces the CKEditor5 check because keyup should always prevent autologout.
          document.addEventListener('keyup', debounce(function (event) {
            document.dispatchEvent(new Event('preventAutologout'));
          }));

          $('body').bind('preventAutologout', function (event) {
            // When the preventAutologout event fires, we set activity to true.
            settings.activity = true;

            // Clear timer if one exists.
            clearTimeout(activityResetTimer);

            // Set a timer that goes off and resets this activity indicator after
            // half a minute, otherwise sessions never timeouts.
            activityResetTimer = setTimeout(function () {
              settings.activity = false;
            }, 30000);
          });

          // On pages where the user should be logged out, set the timer to popup
          // and log them out.
          initTimer = setTimeout(function () {
            init();
          }, localSettings.timeout);
        }
      }

      function init() {
        // Now that a dialog has shown, we clear the one-off timer.
        clearTimeout(initTimer);

        if (settings.activity) {
          keepAlive();
        }
        else {
          // Wait for custom elements to be defined before creating modal
          Promise.all([
            waitForCustomElement('cds-modal'),
            waitForCustomElement('cds-modal-body'),
            waitForCustomElement('cds-modal-footer'),
            waitForCustomElement('cds-button')
          ]).then(() => {
            theModal = createCarbonModal();
            paddingTimer = setTimeout(confirmLogout, localSettings.timeout_padding);
          }).catch(err => {
            console.error('Failed to initialize modal:', err);
            // Fallback to immediate logout if modal can't be created
            logout();
          });
        }
      }

      /**
       * Create Carbon Design System Modal
       */
      function createCarbonModal() {
        // Remove any existing modal first
        let existingModal = document.getElementById('autologout-modal');
        if (existingModal) {
          existingModal.remove();
        }

        // Create modal element
        let modal = document.createElement('cds-modal');
        modal.id = 'autologout-modal';

        // Set attributes properly
        modal.size = 'sm';
        if (localSettings.danger !== false) {
          modal.danger = true;
        }

        // Create modal body
        let body = document.createElement('cds-modal-body');
        body.innerHTML = `
          <p>It appears you have been inactive for a while.</p>
          <p>To protect your information, your progress will be lost and you will be signed out.</p>
        `;
        modal.appendChild(body);

        // Create modal footer with action buttons (if not disabled)
        if (!settings.autologout.disable_buttons) {
          let footer = document.createElement('cds-modal-footer');

          // Primary action button (Stay signed in)
          let primaryButton = document.createElement('cds-button');
          primaryButton.textContent = Drupal.t(settings.autologout.yes_button || 'Yes');
          primaryButton.kind = 'danger';
          primaryButton.size = 'lg';

          // Add click handler
          primaryButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleStaySignedIn();
          });

          footer.appendChild(primaryButton);
          modal.appendChild(footer);
        }

        // Add modal to body BEFORE opening it
        document.body.appendChild(modal);

        // Wait for next frame to ensure element is in DOM
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            // Open the modal using property
            modal.open = true;
          });
        });

        // Listen for modal close events (ESC or backdrop click)
        modal.addEventListener('cds-modal-closed', function(e) {
          logout();
        });

        return modal;
      }

      /**
       * Handle "Stay signed in" button click
       */
      function handleStaySignedIn() {
        setSessionCookie(cookieName, Math.round((new Date()).getTime() / 1000));

        // Close and remove modal
        if (theModal) {
          // Close modal
          theModal.open = false;

          setTimeout(() => {
            if (theModal && theModal.parentNode) {
              theModal.parentNode.removeChild(theModal);
            }
            theModal = null;
          }, 300); // Wait for close animation
        }

        clearTimeout(paddingTimer);
        settings.activity = true;
        clearTimeout(activityResetTimer);
        clearTimeout(initTimer);
        clearTimeout(t);
        refresh();
      }

      // A user could have used the reset button on the tab/window they're
      // actively using, so we need to double check before actually logging out.
      function confirmLogout() {
        if (theModal) {
          theModal.open = false;
          setTimeout(() => {
            if (theModal && theModal.parentNode) {
              theModal.parentNode.removeChild(theModal);
            }
            theModal = null;
          }, 300);
        }
        logout();
      }

      function triggerLogoutEvent(logoutMethod, logoutUrl) {
        const logoutEvent = new CustomEvent('autologout', {
          detail: {
            logoutMethod: logoutMethod,
            logoutUrl: logoutUrl,
          },
        });
        document.dispatchEvent(logoutEvent);
      }

      function logout() {
        if (localSettings.use_alt_logout_method) {
          let logoutUrl = drupalSettings.path.baseUrl + "autologout_alt_logout";
          triggerLogoutEvent('alternative', logoutUrl);

          window.location = logoutUrl;
        }
        else {
          $.ajax({
            url: drupalSettings.path.baseUrl + "autologout_ajax_logout",
            type: "POST",
            beforeSend: function (xhr) {
              xhr.setRequestHeader('X-Requested-With', {
                toString: function () {
                  return '';
                }
              });
            },
            success: function () {
              let logoutUrl = localSettings.redirect_url;
              triggerLogoutEvent('normal', logoutUrl);

              window.location = logoutUrl;
            },
            error: function (XMLHttpRequest, textStatus) {
              if (XMLHttpRequest.status === 403 || XMLHttpRequest.status === 404) {
                window.location = localSettings.redirect_url;
              }
            }
          });
        }
      }

      /**
       * Get the remaining time.
       *
       * Use the Drupal ajax library to handle get time remaining events
       * because if using
       * the JS Timer, the return will update it.
       *
       * @param function callback(time)
       * The function to run when ajax is successful. The time parameter
       * is the time remaining for the current user in ms.
       */
      Drupal.Ajax.prototype.autologoutGetTimeLeft = function (callback) {
        let ajax = this;

        // Store the original success temporary to be called later.
        const originalSuccess = ajax.options.success;
        ajax.options.submit = {
          uactive: settings.activity
        };
        ajax.options.success = function (response, status, xmlhttprequest) {
          if (typeof response == 'string') {
            response = $.parseJSON(response);
          }
          if (typeof response[0].command === 'string' && response[0].command === 'alert') {
            // In the event of an error, we can assume user has been logged out.
            window.location = localSettings.redirect_url;
          }

          // Loop through response to get correct keys.
          for (let key in response) {
            if (response[key].command === "settings" && typeof response[key].settings.time !== 'undefined') {
              callback(response[key].settings.time);
            }
          }

          // Filter out timer insert commands to prevent "60" from showing
          if (Array.isArray(response)) {
            response = response.filter(function(item) {
              // Keep all commands except timer inserts
              if (item.command === "insert") {
                // Filter out if selector is #timer or #autologout-ajax-element
                return item.selector !== '#timer' &&
                  item.selector !== '#autologout-ajax-element' &&
                  !item.selector.includes('timer');
              }
              return true;
            });
          }

          // Let Drupal.ajax handle the JSON response.
          return originalSuccess.call(ajax, response, status, xmlhttprequest);
        };

        try {
          $.ajax(ajax.options);
        }
        catch (e) {
          ajax.ajaxing = false;
        }
      };

      // Create a hidden element for AJAX operations if needed
      let $ajaxElement = $('<div id="autologout-ajax-element" style="display: none;"></div>');
      $('body').append($ajaxElement);

      Drupal.Ajax['autologout.getTimeLeft'] = Drupal.ajax({
        base: null,
        element: $ajaxElement[0],
        url: drupalSettings.path.baseUrl + 'autologout_ajax_get_time_left',
        submit: {
          uactive: settings.activity
        },
        event: 'autologout.getTimeLeft',
        error: function (XMLHttpRequest, textStatus) {
          // Disable error reporting to the screen.
        },
      });

      /**
       * Handle refresh event.
       *
       * Use the Drupal ajax library to handle refresh events because if using
       * the JS Timer, the return will update it.
       *
       * @param function timerFunction
       * The function to tell the timer to run after its been restarted.
       */
      Drupal.Ajax.prototype.autologoutRefresh = function (timerfunction) {
        let ajax = this;

        if (ajax.ajaxing) {
          return false;
        }

        const originalSuccess = ajax.options.success;
        ajax.options.success = function (response, status, xmlhttprequest) {
          // Handle empty or null responses
          if (!response) {
            t = setTimeout(timerfunction, localSettings.timeout);
            return;
          }

          if (typeof response === 'string') {
            try {
              response = $.parseJSON(response);
            } catch (e) {
              console.error('Failed to parse response:', e);
              t = setTimeout(timerfunction, localSettings.timeout);
              return;
            }
          }

          // Check if response is an array and has content
          if (Array.isArray(response) && response.length > 0 &&
            typeof response[0].command === 'string' && response[0].command === 'alert') {
            window.location = localSettings.redirect_url;
            return;
          }

          t = setTimeout(timerfunction, localSettings.timeout);

          // Filter out timer insert commands
          if (Array.isArray(response)) {
            response = response.filter(function(item) {
              if (item.command === "insert") {
                return item.selector !== '#timer' &&
                  item.selector !== '#autologout-ajax-element' &&
                  !item.selector.includes('timer');
              }
              return true;
            });
          }

          // Only call original success if it exists and response has content
          if (originalSuccess && response) {
            return originalSuccess.call(ajax, response, status, xmlhttprequest);
          }
        };

        ajax.options.error = function(XMLHttpRequest, textStatus) {
          // On error, still set the timer
          t = setTimeout(timerfunction, localSettings.timeout);
          ajax.ajaxing = false;
        };

        try {
          $.ajax(ajax.options);
        }
        catch (e) {
          console.error('AJAX error:', e);
          ajax.ajaxing = false;
          t = setTimeout(timerfunction, localSettings.timeout);
        }
      };

      Drupal.Ajax['autologout.refresh'] = Drupal.ajax({
        base: null,
        element: $ajaxElement[0],
        url: drupalSettings.path.baseUrl + 'autologout_ajax_set_last',
        event: 'autologout.refresh',
        error: function (XMLHttpRequest, textStatus) {
          // Disable error reporting to the screen.
        }
      });

      function keepAlive() {
        if (!document.hidden) {
          Drupal.Ajax['autologout.refresh'].autologoutRefresh(keepAlive);
        } else {
          t = setTimeout(keepAlive, localSettings.timeout);
        }
      }

      function refresh() {
        Drupal.Ajax['autologout.refresh'].autologoutRefresh(init);
      }

      // Check if the page was loaded via a back button click.
      let $dirty_bit = $('#autologout-cache-check-bit');
      if ($dirty_bit.length !== 0) {
        if ($dirty_bit.val() === '1') {
          // Page was loaded via back button click, we should refresh the timer.
          keepAlive();
        }

        $dirty_bit.val('1');
      }
    }
  };

})(jQuery, Drupal, window.Cookies);
