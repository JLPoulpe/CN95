import { Controller } from '@hotwired/stimulus';

// Fiche de sécu : glisser-déposer natif d'une personne vers une palanquée ou vers les restants.
// Chaque dépôt est enregistré aussitôt ; le plateau est ensuite rechargé depuis le serveur (compteurs et avertissements à jour).
export default class extends Controller {
    static targets = ['plateau', 'zone', 'erreur'];
    static values = { url: String, token: String };

    debut(event) {
        event.dataTransfer.setData('text/plain', event.currentTarget.dataset.userId);
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
        const userId = event.dataTransfer.getData('text/plain');
        if (!userId) {
            return;
        }

        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ userId: Number(userId), palanqueeId: zone.dataset.palanqueeId || null, _token: this.tokenValue }),
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
