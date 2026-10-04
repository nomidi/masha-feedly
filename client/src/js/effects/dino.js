/** Pixel-Dino frisst nach erfolgreichem Abschluss den Speichern-Button. */
(() => {
  // Gelieferte T-Rex-Form (fi_5016197), mit derselben Farbaufteilung.
  const dinoArtwork = '<path d="m50.32 45.07-2.23 5.49-2.35-2.53c-2.403-2.5916037-3.7392907-5.9954449-3.74-9.53v-14.5c0-4.5086803-1.7910663-8.8326968-4.9791847-12.0208153-3.1881185-3.18811841-7.512135-4.9791847-12.0208153-4.9791847h-.76v-.12l2.85-5.7c.3630611-.72181428 1.1020215-1.17730026 1.91-1.17730026s1.5469389.45548598 1.91 1.17730026l1.23 2.46c.487131.97930194 1.6378178 1.42919451 2.66 1.04l3.31-1.24c.6564049-.24562895 1.3914858-.15427288 1.967797.24455838.5763111.39883127.9208049 1.05458572.922203 1.75544162v3.03c-.0029999 1.0780779.7999793 1.9884073 1.87 2.12l2.8.35c.7003354.0866449 1.3031772.5359464 1.5863479 1.1823143.2831707.6463678.2047546 1.3941251-.2063479 1.9676857l-1.68 2.34c-.3476277.4934141-.4719556 1.1101812-.3426333 1.6997389.1293224.5895577.500395 1.0976563 1.0226333 1.4002611l2.52 1.44c.580428.3295557.9732297.9117031 1.0615723 1.5732915.0883427.6615884-.1379505 1.3264043-.6115723 1.7967085l-3.04 3.04c-.4712512.4714126-.6959291 1.1354924-.6077052 1.7961921.088224.6606996.4792739 1.2425606 1.0577052 1.5738079l2.47 1.41c.5173397.2966689.8758582.8081399.978285 1.3956444s-.0618592 1.1901223-.448285 1.6443556l-2.64 3.08c-.4298584.4979018-.6058173 1.1663491-.4768145 1.8113629.1290027.6450138.5485214 1.1943656 1.1368145 1.4886371l1.9.95c.9300262.4697843 1.3501402 1.5698766.97 2.54z" fill="#a4c400"/><path d="m52.71 58h-35.48c-.6142004.0018461-1.1951895-.2786249-1.575777-.7607024-.3805874-.4820775-.5185635-1.1122957-.374223-1.7092976.4165639-1.7739161 1.4474061-3.3432052 2.91-4.43-.4215263-.8171965-.6182434-1.7317587-.57-2.65.025012-.4819347.1227848-.9573128.29-1.41-3.58-7.75 3.09-14.04 3.09-14.04h-8c-3.63713342.0008371-6.81707747-2.4519104-7.74-5.97.24927004-.0752954.46257045-.2388257.6-.46l2.14-3.57 2.14 3.57c.1801377.3035865.5069924.489706.86.489706s.6798623-.1861195.86-.489706l2.14-3.57h-12c-1.01580246-.0284934-1.85160213-.8085731-1.95-1.82-.03-.39-.05-.78-.05-1.18.00441308-7.177873 5.822127-12.99558692 13-13h12c9.3888407 0 17 7.6111593 17 17v14.5c.0007093 3.5345551 1.3365294 6.9383963 3.74 9.53l2.35 2.53 5.35 5.76c.2699902.2911137.342181.7143824.1839495 1.0785316-.1582315.3641493-.5169101.600195-.9139495.6014684z" fill="#60a917"/><path d="m14 23-2.14 3.57c-.1801377.3035865-.5069924.489706-.86.489706s-.6798623-.1861195-.86-.489706L8 23z" fill="#f5f5f5"/><circle cx="21.5" cy="15.5" r="4.5" fill="#f5f5f5"/><path d="m21.5 11c-.5121672.0048915-1.0196359.0982658-1.5.276 1.7962.627062 2.9993814 2.3214908 2.9993814 4.224s-1.2031814 3.596938-2.9993814 4.224c.4803641.1777342.9878328.2711085 1.5.276 2.4852813-.0000001 4.4999999-2.0147187 4.4999999-4.5s-2.0147186-4.5-4.4999999-4.5z" fill="#cfd8dc"/><path d="m31.909 39.292c.9639386.0001456 1.9020286-.3117325 2.674-.889l4.017-2.981c.4434846-.3313709.5343708-.9595153.203-1.403-.3313709-.4434846-.9595153-.5343708-1.403-.203l-4.01 2.984c-.7165988.5406777-1.668071.6556442-2.4928264.3012084s-1.3961656-1.1238556-1.4970763-2.0158552.2841714-1.7696261 1.0089027-2.2993532l4.014-2.986c.3105861-.2039908.4824522-.5633757.4462758-.9331962-.0361763-.3698205-.2744289-.6890871-.6186584-.829023-.3442294-.1399359-.7376663-.0774633-1.0216174.1622192l-4.012 2.982c-1.4608786 1.0851625-2.1243578 2.9433945-1.6811329 4.7084145.4432249 1.7650201 1.9058945 3.0893419 3.7061329 3.3555855.2209594.0311476.4438566.0465198.667.046z" fill="#000"/><circle cx="22" cy="17" r="1" fill="#000"/><path d="m7 17c-.55228475 0-1 .4477153-1 1v2c0 .5522847.44771525 1 1 1s1-.4477153 1-1v-2c0-.5522847-.44771525-1-1-1z" fill="#000"/><path d="m11 17c-.5522847 0-1 .4477153-1 1v2c0 .5522847.4477153 1 1 1s1-.4477153 1-1v-2c0-.5522847-.4477153-1-1-1z" fill="#000"/>';

  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__dino-effect';
    layer.setAttribute('aria-hidden', 'true');
    let target = null;
    try {
      target = [...(document.querySelectorAll?.('button.kw-masha-feedly__submit[type="submit"]') || [])]
        .find((button) => button.getClientRects?.().length);
    } catch (_) { /* Vorschau und ältere Browser verwenden die mittige Ersatzfläche. */ }
    const bounds = target?.getBoundingClientRect?.();
    const viewportWidth = Number(window.innerWidth) || 1024;
    const viewportHeight = Number(window.innerHeight) || 768;
    const left = bounds ? Math.max(12, Math.min(bounds.left, viewportWidth - bounds.width - 12)) : Math.max(12, viewportWidth / 2 - 70);
    const top = bounds ? Math.max(12, Math.min(bounds.top, viewportHeight - (bounds.height || 44) - 12)) : Math.max(12, viewportHeight * 0.66);
    const width = bounds?.width || 140;
    layer.style.setProperty('--dino-target-left', `${left}px`);
    layer.style.setProperty('--dino-target-top', `${top}px`);
    layer.style.setProperty('--dino-target-width', `${width}px`);
    layer.style.setProperty('--dino-bite-left', `${left + width - 48}px`);
    layer.style.setProperty('--dino-target-center', `${left + width / 2}px`);

    const saveButton = document.createElement('span');
    saveButton.className = 'kw-masha-feedly__dino-save-copy';
    saveButton.textContent = target?.textContent?.trim() || 'Speichern';
    layer.append(saveButton);
    const dino = document.createElement('span');
    dino.className = 'kw-masha-feedly__dino-character';
    dino.innerHTML = `<svg viewBox="0 0 56 60" focusable="false" aria-hidden="true"><g transform="translate(1 1)">${dinoArtwork}</g></svg>`;
    layer.append(dino);
    const bite = document.createElement('span');
    bite.className = 'kw-masha-feedly__dino-bite';
    bite.textContent = 'HAPS!';
    layer.append(bite);
    for (let i = 0; i < 10; i += 1) {
      const pixel = document.createElement('i');
      pixel.className = 'kw-masha-feedly__dino-pixel';
      pixel.setAttribute?.('aria-hidden', 'true');
      layer.append(pixel);
    }
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 3400);
    return { dino: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.dino = { play };
})();
