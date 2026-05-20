document.addEventListener("DOMContentLoaded", async () => {
  const file = window.location.pathname.split("/").pop();

  if (file === "login.html") {
    return;
  }

  const user = await requireAuth();

  if (!user) {
    return;
  }

  const moderatorPages = [
    "admin-users.html",
    "admin-groups.html",
    "admin-anonymous-boxes.html",
    "admin-assignments.html",
    "admin-rules.html",
    "admin-moderation.html",
    "admin-import-export.html"
  ];
  const adminPages = ["admin-audit-logs.html"];

  if (user.role === "user" && (moderatorPages.includes(file) || adminPages.includes(file))) {
    window.location.href = "dashboard.html";
    return;
  }

  if (user.role !== "admin" && adminPages.includes(file)) {
    window.location.href = "dashboard.html";
    return;
  }

  const links = [
    { href: "dashboard.html", label: "Dashboard", roles: ["user", "moderator", "admin"] },
    { href: "messages.html", label: "Messages", roles: ["user", "moderator", "admin"] },
    { href: "compose.html", label: "Compose", roles: ["user", "moderator", "admin"] },
    { href: "topics.html", label: "Topics", roles: ["user", "moderator", "admin"] },
    { href: "my-reviews.html", label: "My Reviews", roles: ["user", "moderator", "admin"] },
    { href: "rules.html", label: "Rules", roles: ["user", "moderator", "admin"] },
    { href: "admin-users.html", label: "Users", roles: ["moderator", "admin"] },
    { href: "admin-groups.html", label: "Groups", roles: ["moderator", "admin"] },
    { href: "admin-anonymous-boxes.html", label: "Anonymous Boxes", roles: ["moderator", "admin"] },
    { href: "admin-assignments.html", label: "Assignments", roles: ["moderator", "admin"] },
    { href: "admin-rules.html", label: "Admin Rules", roles: ["moderator", "admin"] },
    { href: "admin-moderation.html", label: "Moderation", roles: ["moderator", "admin"] },
    { href: "admin-import-export.html", label: "Import/Export", roles: ["moderator", "admin"] },
    { href: "admin-audit-logs.html", label: "Audit Logs", roles: ["admin"] }
  ];

  const nav = document.querySelector(".main-nav");

  if (nav) {
    nav.innerHTML = links
      .filter((link) => link.roles.includes(user.role))
      .map((link) => {
        const active = link.href === file ? ' class="active"' : "";
        return `<a href="${link.href}"${active}>${link.label}</a>`;
      })
      .join("");
  }

  const header = document.querySelector(".header-inner");
  let logoutButton = document.getElementById("logout-button");

  if (header && !logoutButton) {
    logoutButton = document.createElement("button");
    logoutButton.type = "button";
    logoutButton.className = "button";
    logoutButton.id = "logout-button";
    logoutButton.textContent = "Logout";
    header.appendChild(logoutButton);
  }

  if (logoutButton) {
    logoutButton.addEventListener("click", logout);
  }
});
