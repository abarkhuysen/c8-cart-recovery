# WordPress Workflow: Symlinks
Your plugin lives in a separate folder; symlinked into the WP installation.  
[Local WordPress with Herd, DBngin, and WP-CLI](https://www.youtube.com/watch?v=RbUeMH-a8vU)

**Do not build your plugin directly inside the WordPress folder.** If you delete your test site, you lose your plugin. Instead, keep your plugin in its own Git repository and "project" it into WordPress using a symlink. Herd handles this beautifully.

1. **Create your plugin repo** somewhere else (e.g., ```~/Code/my-awesome-plugin```).
2. **Link it to WordPress**
```shell
cd ~/Herd/wp-sandbox/wp-content/plugins
ln -s ~/Code/my-awesome-plugin my-awesome-plugin
```

Now, you edit files in ~/Code/my-awesome-plugin, commit them to Git, and they instantly update in your local WordPress site. You can symlink this single plugin folder to multiple WordPress installs (e.g., a "clean" install and a "bloated" install with other plugins) to test compatibility.

