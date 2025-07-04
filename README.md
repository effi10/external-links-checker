# external-links-checker

* **Contributeurs :** Cédric GIRARD
* **Version :** 1.2.0
* **Requiert WordPress :** 5.8 ou supérieur
* **Testé jusqu'à :** 6.8
* **Licence :** GPLv2 ou ultérieure
* **URI de la licence :** https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress conçu pour compter le nombre de liens sortants dans les articles, avec la possibilité de les filtrer selon une classe CSS de votre choix (pour éviter de compter les liens masqués).

## Plugin dédié aux éditeurs de site

External Links Checker permet de détecter les liens sortants de vos articles de blog, et de les afficher dans la liste des posts de WordPress (colonne triable).

![image](https://github.com/user-attachments/assets/d27b9f99-478d-40f2-b93f-62ba90036e76)


Version 1.1 : 
- Ajout d'un bloc permettant de voir le pourcentage de posts avec au moins un lien externe.
![image](https://github.com/user-attachments/assets/b3a11b0a-f725-406b-a2bf-afdeda1ebf3c)

Version 1.2 : 
- Déplacement de l'onglet du plugin dans "Outils"
- Ajout de statistiques par catégories dans l'onglet de paramétrage, avec liens cliquables qui mènent à la liste des articles pour chaque catégorie
![image](https://github.com/user-attachments/assets/b3a48ccd-0d77-4c6c-a3af-ca7d2259baed)

Ainsi qu'une visualisation graphique :
![image](https://github.com/user-attachments/assets/e63f6f7e-f2bf-43c4-9b28-a319c1783b27)

- FIX : gère désormais le contenu tel qu'affiché en back-office (permet de prendre en considération les plugins d'obfuscation ou les obfuscations de liens automatiques) ; peut ralentir l'analyse des articles existants sur de gros sites (exemple : 2 à 3 minutes pour un site avec ~500 articles)

## Réglages et utilisation

Dans l'onglet "Outils" du back-office, vous pouvez accéder à une page dédiée "External Link Checker" offrant deux onglets :
- Paramètres, qui permet (en option) d'indiquer une éventuelle classe CSS attribuée à vos liens afin d'éviter de les comptabiliser (si vous utilisez l'obfuscation de liens, très pratique !) et d'avoir des statistiques 
- Export, qui permet d'exporter la liste des posts concernés au format CSV avec les informations utiles sur ces derniers

Remarque : certains embeds génèrent parfois des liens cachés dans le code (cas des embeds YouTube, qui génèrent un lien NoFollow dans une balise NoScript), ce qui peut générer de faux positifs
