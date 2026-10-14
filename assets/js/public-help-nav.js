const publicNav = document.querySelector('#nav');
const publicNavLinks = publicNav?.querySelector('.navlinks');
const publicHamburger = publicNav?.querySelector('.hamburger');
const helpNavTrigger = publicNav?.querySelector('.help-nav-trigger');
const helpNavMenu = publicNav?.querySelector('.help-nav-menu');

if (publicNav && publicNavLinks && publicHamburger) {
  publicHamburger.addEventListener('click', () => {
    const isOpen = publicNavLinks.classList.toggle('open');
    publicHamburger.classList.toggle('active', isOpen);
    publicHamburger.setAttribute('aria-expanded', String(isOpen));
  });

  publicNavLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      publicNavLinks.classList.remove('open');
      publicHamburger.classList.remove('active');
      publicHamburger.setAttribute('aria-expanded', 'false');
    });
  });
}

if (publicNav && helpNavTrigger && helpNavMenu) {
  helpNavTrigger.addEventListener('click', event => {
    event.stopPropagation();
    const isOpen = helpNavMenu.classList.toggle('open');
    helpNavTrigger.setAttribute('aria-expanded', String(isOpen));
  });

  document.addEventListener('click', event => {
    if (!publicNav.contains(event.target)) {
      publicNavLinks?.classList.remove('open');
      publicHamburger?.classList.remove('active');
      publicHamburger?.setAttribute('aria-expanded', 'false');
    }
    if (!helpNavMenu.contains(event.target) && !helpNavTrigger.contains(event.target)) {
      helpNavMenu.classList.remove('open');
      helpNavTrigger.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && helpNavMenu.classList.contains('open')) {
      helpNavMenu.classList.remove('open');
      helpNavTrigger.setAttribute('aria-expanded', 'false');
      helpNavTrigger.focus();
    }
  });
}

const publicTicketDialog = document.querySelector('.public-ticket-dialog');

if (publicTicketDialog) {
  const ticketCloseLink = publicTicketDialog.querySelector('.public-ticket-close');
  const ticketFocusableElements = () => Array.from(publicTicketDialog.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter(element => element.getClientRects().length > 0);

  ticketFocusableElements()[0]?.focus({ preventScroll: true });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      if (ticketCloseLink) {
        window.location.assign(ticketCloseLink.href);
      }
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    const focusableElements = ticketFocusableElements();
    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (!firstElement || !lastElement) {
      event.preventDefault();
    } else if (event.shiftKey && (document.activeElement === firstElement || !publicTicketDialog.contains(document.activeElement))) {
      event.preventDefault();
      lastElement.focus();
    } else if (!event.shiftKey && (document.activeElement === lastElement || !publicTicketDialog.contains(document.activeElement))) {
      event.preventDefault();
      firstElement.focus();
    }
  });
}
