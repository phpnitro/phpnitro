(() => {
  let known = null;

  setInterval(() => {
    fetch('/_dev/version')
      .then((r) => r.json())
      .then(({ version }) => {
        if (known === null) {
          known = version;
        } else if (version !== known) {
          window.location.reload();
        }
      })
      .catch(() => {});
  }, 1000);
})();
