import { Controller } from '@hotwired/stimulus';

/*
 * Liste des utilisateurs : recherche à la frappe, filtres, tri par en-tête, ligne cliquable.
 * Amélioration progressive : sans JavaScript, le formulaire GET (?q=) reste fonctionnel.
 */
export default class extends Controller {
    static targets = ['recherche', 'role', 'aptitude', 'mineur', 'caci', 'ligne', 'compteur', 'vide', 'tri', 'corps'];

    connect() {
        this.cle = null;
        this.sens = 1;
        this.minuteur = null;
        this.element.querySelectorAll('[data-js-only]').forEach((e) => { e.hidden = false; });
        this.element.querySelectorAll('[data-no-js]').forEach((e) => { e.hidden = true; });
        this.filtrer();
    }

    normaliser(t) {
        return (t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    // Empêche l'envoi du formulaire (Entrée) : le filtrage est déjà fait
    soumettre(event) {
        event.preventDefault();
        this.filtrer();
    }

    differer() {
        clearTimeout(this.minuteur);
        this.minuteur = setTimeout(() => this.filtrer(), 150);
    }

    filtrer() {
        const q = this.normaliser(this.rechercheTarget.value.trim());
        const role = this.roleTarget.value;
        const apt = this.aptitudeTarget.value;
        const mineur = this.mineurTarget.value;
        const caci = this.caciTarget.value;
        let visibles = 0;
        this.ligneTargets.forEach((l) => {
            const d = l.dataset;
            const ok = (!q || d.recherche.includes(q))
                && (!role || d.roles.split('|').includes(role))
                && (!apt || d.aptitude === apt)
                && (!mineur || d.mineur === mineur)
                && (!caci || d.caci === caci);
            l.hidden = !ok;
            if (ok) visibles++;
        });
        this.compteurTarget.textContent = `${visibles} résultat${visibles > 1 ? 's' : ''} sur ${this.ligneTargets.length}`;
        this.videTarget.hidden = visibles > 0;
    }

    reinitialiser() {
        this.rechercheTarget.value = '';
        [this.roleTarget, this.aptitudeTarget, this.mineurTarget, this.caciTarget].forEach((s) => { s.value = ''; });
        this.filtrer();
    }

    trier(event) {
        const th = event.currentTarget.closest('th');
        const cle = event.currentTarget.dataset.cle;
        this.sens = this.cle === cle ? -this.sens : 1;
        this.cle = cle;
        const lignes = [...this.ligneTargets];
        lignes.sort((a, b) => {
            const va = a.dataset['tri' + cle] || '';
            const vb = b.dataset['tri' + cle] || '';
            // Valeurs vides toujours en fin de liste
            if (!va !== !vb) return va ? -1 : 1;
            return va.localeCompare(vb, 'fr', { numeric: true }) * this.sens;
        });
        lignes.forEach((l) => this.corpsTarget.appendChild(l));
        this.triTargets.forEach((b) => {
            const h = b.closest('th');
            h.setAttribute('aria-sort', h === th ? (this.sens === 1 ? 'ascending' : 'descending') : 'none');
        });
    }

    ouvrir(event) {
        if (event.target.closest('a, button, input, select')) return;
        const lien = event.currentTarget.querySelector('a[data-fiche]');
        if (lien) window.location.href = lien.href;
    }
}
