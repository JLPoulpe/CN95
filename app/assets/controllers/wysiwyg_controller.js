import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';

/*
 * Remplace le textarea (data-controller="wysiwyg") par un éditeur riche.
 * Le HTML produit est recopié dans le textarea à chaque modification, donc le formulaire l'envoie tel quel.
 */
export default class extends Controller {
    connect() {
        this.textarea = this.element;
        this.textarea.hidden = true;

        this.conteneur = document.createElement('div');
        this.textarea.after(this.conteneur);

        this.editeur = new Quill(this.conteneur, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['link'],
                    ['clean'],
                ],
            },
        });
        // La hauteur suit l'attribut rows du textarea (4 lignes = 4 lignes d'écriture)
        this.conteneur.querySelector('.ql-editor').style.minHeight = `${this.textarea.rows * 1.5}em`;
        this.editeur.clipboard.dangerouslyPasteHTML(this.textarea.value);
        this.editeur.on('text-change', this.synchroniser);
    }

    disconnect() {
        this.editeur.off('text-change', this.synchroniser);
        this.conteneur.remove();
        this.textarea.hidden = false;
    }

    synchroniser = () => {
        // getSemanticHTML évite les éléments d'interface de Quill (puces, numéros) dans le HTML enregistré
        this.textarea.value = this.editeur.getSemanticHTML();
    };
}
