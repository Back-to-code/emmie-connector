// On the Plugins screen, deactivating Emmie Connector first asks whether to delete its settings and its form page.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.wp2emmie-deactivate').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var deactivateUrl = el.dataset.deactivateUrl;

            if (window.confirm(window.wp2emmiePluginsPage.confirmDelete)) {
                window.location.href = deactivateUrl + '&wp2emmie_delete=1';
            } else {
                window.location.href = deactivateUrl;
            }
        });
    });
});
