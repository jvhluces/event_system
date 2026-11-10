document.addEventListener("DOMContentLoaded", function() {
    const menu = document.getElementById("notificationMenu");
    const toggle = document.getElementById("notificationToggle");
    const panel = document.getElementById("notificationPanel");

    if (!menu || !toggle || !panel) {
        return;
    }

    function closePanel() {
        panel.hidden = true;
        toggle.setAttribute("aria-expanded", "false");
    }

    toggle.addEventListener("click", function() {
        const isOpen = toggle.getAttribute("aria-expanded") === "true";
        panel.hidden = isOpen;
        toggle.setAttribute("aria-expanded", String(!isOpen));
    });

    document.addEventListener("click", function(event) {
        if (!menu.contains(event.target)) {
            closePanel();
        }
    });

    document.addEventListener("keydown", function(event) {
        if (event.key === "Escape") {
            closePanel();
        }
    });
});
