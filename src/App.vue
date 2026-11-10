<template>
  <div class="app">
    <header class="app-header">
      <div class="header-inner">
        <router-link to="/" class="app-title">Galerie Photos</router-link>
      </div>
    </header>
    <main class="app-main">
      <router-view />
    </main>
    <footer class="app-footer">
      <span>© 2026 Maud Robin</span>
      <span class="sep">|</span>
      <a href="https://maudrobin.fr">maudrobin.fr</a>
    </footer>
  </div>
</template>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: "Lato", sans-serif;
  background: #fff;
  color: #444;
  min-height: 100vh;
  padding-bottom: 52px;
}

a {
  color: inherit;
  text-decoration: none;
}

.app-header {
  background: #fff;
  border-bottom: 1px solid #e8e0d0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  position: sticky;
  top: 0;
  z-index: 200;
}

.header-inner {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 20px;
  height: 64px;
  display: flex;
  align-items: center;
}

.app-title {
  font-size: 1.4rem;
  font-weight: 600;
  color: #183a63;
  letter-spacing: 0.02em;
}

.app-title:hover {
  color: #b89a5a;
}

.app-main {
  max-width: 1100px;
  margin: 0 auto;
  padding: 2rem 20px;
  overflow-x: hidden;
}

.app-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: #183a63;
  color: #fff;
  text-align: center;
  padding: 12px 20px;
  font-size: 13px;
  z-index: 100;
}

.app-footer a {
  color: #d4bfa0;
  text-decoration: none;
}

.app-footer a:hover {
  color: #fff;
  text-decoration: underline;
}

.app-footer .sep {
  margin: 0 10px;
  opacity: 0.5;
}

@media (max-width: 768px) {
  .header-inner {
    height: 48px;
    padding: 0 12px;
  }

  .app-title {
    font-size: 1.1rem;
  }

  .app-main {
    padding: 1rem 10px;
  }

  .app-footer {
    padding: 8px 12px;
    font-size: 11px;
  }

  body {
    padding-bottom: 40px;
  }
}
</style>
