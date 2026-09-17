# ADR-19 — Vite dev server se pokreće na hostu, ne u kontejneru

> Nastavak serije iz [`docs/design.md`](../design.md), gde su ADR-01 do ADR-09.

**Datum:** 2026-09-17 · **Status:** prihvaćeno

**Kontekst:** do ovog tiketa frontend je bio Blade i nekoliko linija Tailwind-a, pa je bilo dovoljno da se asseti grade u `frontend-assets` stage-u pri build-u image-a. React SPA menja tu računicu: rad na komponentama znači stotine izmena. Runtime `app` kontejner nema Node ni npm — to je namerno, po FR-41, FR-42 i C-06, i `docker compose exec app sh -c 'command -v node'` vraća `ABSENT`. Uz to, `public/build` je imenovani volume `app-build`, a Docker ga puni iz image-a **samo dok je prazan** ([ADR-04](../design.md), rizik R-04). Svaka izmena JSX-a bi zato tražila `docker compose down`, ručno uklanjanje volumena i pun rebuild.

**Opcije:** (a) `npm run dev` na hostu, Vite servira module sa `localhost:5173`; (b) dodati četvrti Compose servis sa Node image-om koji drži `npm run dev` i izložiti 5173; (c) ostaviti kako jeste i graditi image posle svake izmene.

**Odluka:** (a).

**Obrazloženje:** (c) je izmereno neupotrebljivo — ciklus je rebuild sa četiri stage-a plus brisanje volumena, po izmeni jedne komponente. To ne bi bilo sporo razvijanje nego prestanak razvijanja.

(b) je tehnički ispravno i za veće timove uobičajeno, ali u ovo okruženje ulazi skupo: četvrti servis, `node_modules` kao još jedan imenovani volume, `server.host` i `hmr.host` podešavanja da bi HMR prošao kroz granicu kontejnera, i novi izvor neslaganja uid/gid nad bind mount-om — tačno klasa problema koju ADR-07 već rešava za `app`. Za jednog developera na WSL2 to je infrastruktura bez korisnika.

(a) prolazi zato što je jedan detalj okruženja već tu: koren projekta je bind-mount-ovan na `/var/www/html`, pa `public/hot`, koji Vite piše na hostu, PHP vidi unutar kontejnera. Blade direktiva `@vite` proverava postojanje tog fajla i tada šalje browser na dev server umesto na `public/build`. Browser i Vite su oba na hostu, pa nema mrežne granice koju treba premošćavati. Node na hostu je ionako prisutan — `npm ci` se izvršava pri svakom build-u image-a iz istog `package-lock.json`.

**Posledice:** pojavljuje se korak koji `README.md` ranije nije imao — `npm run dev` pre rada na frontendu, i to je jedina komanda ovog projekta koja se namerno pokreće **na hostu**, nasuprot pravilu iz `CLAUDE.md` da sve ide kroz `app` kontejner. Izuzetak se mora napisati, inače protivreči pravilu. Za proveru proizvodnog bundle-a i dalje važi procedura iz `README.md` sekcije 8: `docker compose down`, `docker volume rm dockertask_app-build`, pa `docker compose up -d --build` — jer bez uklanjanja volumena novi bundle ne stiže do Apache-a, a build prolazi bez greške i simptom izgleda kao da izmena nije ni napisana. `public/hot` je već u `.gitignore`.
