import { Controller } from '@hotwired/stimulus';

// Fiche de sécu : glisser-déposer natif d'une personne vers une palanquée ou vers les restants.
// Chaque dépôt est enregistré aussitôt ; le plateau est ensuite rechargé depuis le serveur (compteurs et avertissements à jour).
export default class extends Controller {
    static targets = ['plateau', 'zone', 'erreur'];
    static values = { url: String, token: String };

    debut(event) {
        // Une personne est identifiée par son id, un moniteur extérieur (sans compte) par « ext:numéro »
        const { userId, exterieur } = event.currentTarget.dataset;
        event.dataTransfer.setData('text/plain', exterieur ? `ext:${exterieur}` : userId);
        event.dataTransfer.effectAllowed = 'move';
        event.currentTarget.classList.add('is-dragging');
    }

    fin(event) {
        event.currentTarget.classList.remove('is-dragging');
        this.zoneTargets.forEach((z) => z.classList.remove('is-over'));
    }

    survol(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('is-over');
    }

    sortie(event) {
        // dragleave se déclenche aussi en passant sur un enfant : on ne retire la surbrillance qu'en quittant vraiment la zone
        if (!event.currentTarget.contains(event.relatedTarget)) {
            event.currentTarget.classList.remove('is-over');
        }
    }

    async depot(event) {
        event.preventDefault();
        const zone = event.currentTarget;
        zone.classList.remove('is-over');
        const donnee = event.dataTransfer.getData('text/plain');
        if (!donnee) {
            return;
        }
        await this.deplacer(donnee, zone.dataset.palanqueeId || null);
    }

    // Liste déroulante d'une palanquée : la sélection ajoute aussitôt la personne
    async ajouter(event) {
        const select = event.currentTarget;
        const donnee = select.value;
        if (!donnee) {
            return;
        }
        await this.deplacer(donnee, select.closest('[data-palanquee-id]').dataset.palanqueeId);
    }

    // Bouton ✕ : la personne retourne aux restants
    async retirer(event) {
        event.preventDefault();
        const li = event.currentTarget.closest('li');
        const { userId, exterieur } = li.dataset;
        await this.deplacer(exterieur ? `ext:${exterieur}` : userId, null);
    }

    // Paramètres de plongée : enregistrés à chaque modification, sans recharger le plateau (le focus est conservé)
    async parametres(event) {
        const bloc = event.currentTarget.closest('.fiche-params');
        const champs = {};
        bloc.querySelectorAll('input, select').forEach((c) => {
            champs[c.name] = c.value;
        });
        try {
            const url = `${this.urlValue.replace(/\/deplacer$/, '')}/palanquees/${bloc.dataset.palanqueeId}/parametres`;
            const reponse = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...champs, _token: this.tokenValue }),
            });
            const donnees = await reponse.json();
            if (!reponse.ok || !donnees.ok) {
                throw new Error(donnees.message || 'Enregistrement impossible.');
            }
            this.erreurTarget.hidden = true;
        } catch (e) {
            this.erreurTarget.textContent = e.message;
            this.erreurTarget.hidden = false;
        }
    }

    async deplacer(donnee, palanqueeId) {
        const personne = donnee.startsWith('ext:') ? { exterieur: Number(donnee.slice(4)) } : { userId: Number(donnee) };

        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...personne, palanqueeId, _token: this.tokenValue }),
            });
            const donnees = await reponse.json();
            if (!reponse.ok || !donnees.ok) {
                throw new Error(donnees.message || 'Enregistrement impossible.');
            }
            this.erreurTarget.hidden = true;
            await this.rafraichir();
        } catch (e) {
            this.erreurTarget.textContent = e.message;
            this.erreurTarget.hidden = false;
        }
    }

    // Remplace le plateau par celui renvoyé par le serveur (état enregistré)
    async rafraichir() {
        const reponse = await fetch(window.location.href, { headers: { Accept: 'text/html' } });
        const html = new DOMParser().parseFromString(await reponse.text(), 'text/html');
        const nouveau = html.querySelector('[data-fiche-securite-target="plateau"]');
        if (nouveau) {
            this.plateauTarget.innerHTML = nouveau.innerHTML;
        }
    }
}
