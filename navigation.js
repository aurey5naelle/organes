document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('.sidebar');
    const menuItems = document.querySelectorAll('.sidebar-menu .menu-item');

    if (!sidebar || !menuItems.length) return;

    const currentPath = window.location.pathname.split('/').pop();
    const currentPage = currentPath.endsWith('.html')
        ? currentPath.replace(/\.html$/, '.php')
        : currentPath;

    menuItems.forEach((menuItem) => {
        const href = menuItem.getAttribute('href');
        const targetPath = new URL(menuItem.href, window.location.href).pathname.split('/').pop();
        const targetPage = targetPath.endsWith('.html')
            ? targetPath.replace(/\.html$/, '.php')
            : targetPath;
        const isCurrentPage = href !== '#' && targetPage === currentPage;

        menuItem.classList.toggle('active', isCurrentPage);
        menuItem.toggleAttribute('aria-current', isCurrentPage);
    });

    const toggle = document.createElement('button');
    toggle.className = 'navigation-toggle';
    toggle.type = 'button';
    toggle.setAttribute('aria-label', 'Ouvrir la navigation');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';

    const backdrop = document.createElement('div');
    backdrop.className = 'navigation-backdrop';

    const closeNavigation = () => {
        sidebar.classList.remove('is-open');
        backdrop.classList.remove('is-visible');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Ouvrir la navigation');
    };

    toggle.addEventListener('click', () => {
        const isOpen = sidebar.classList.toggle('is-open');
        backdrop.classList.toggle('is-visible', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Fermer la navigation' : 'Ouvrir la navigation');
    });

    backdrop.addEventListener('click', closeNavigation);
    menuItems.forEach((menuItem) => menuItem.addEventListener('click', closeNavigation));
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeNavigation();
    });

    document.body.append(toggle, backdrop);
});
