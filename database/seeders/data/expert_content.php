<?php

/**
 * Contenu politique réel, transcrit tel quel depuis
 * PolitiMatch-Document-Expert-Politique-Complete.pdf (30 questions, 6 partis
 * francophones, positions -2..+2 sourcées par un politologue spécialisé).
 *
 * Aucune valeur n'est inventée ni "nettoyée" ici : ce fichier est une
 * transcription fidèle. Les rares champs absents du document (ex. couleurs
 * de marque, non fournies par l'expert car hors de son périmètre) sont
 * signalés par un commentaire plutôt que comblés par une valeur plausible.
 *
 * axe_ideologique ('economique' | 'societal' | 'aucun') classe chaque
 * question pour le calcul des DEUX AXES du graphique 2D — distinct du
 * matching utilisateur-partis (ScoreCalculator), qui continue d'utiliser les
 * 30 questions et leurs poids sans aucun changement. Ce champ s'appelait à
 * l'origine `axis_type` et suivait une classification par thématique entière
 * (Économie + Environnement + Social → axe économique, Immigration +
 * Société → axe sociétal) — une première approximation qui s'est révélée
 * trop grossière : elle regroupait par exemple les 6 questions Environnement
 * dans l'axe sociétal, ce qui faisait basculer le PTB du côté "libéral" de
 * l'axe économique et inversait l'ordre MR/Écolo sur l'axe sociétal.
 * Reclassifié question par question par l'expert politique sur la seule base de la
 * pureté idéologique de chaque question (rapport réel à l'intervention de
 * l'État/la redistribution pour l'axe économique, à l'ouverture/l'inclusion
 * pour l'axe sociétal) — cf. PolitiMatch-Tableau-Axes-Ideologiques.pdf pour
 * la justification détaillée de chaque question. Les questions qui relèvent
 * d'une troisième dimension que le modèle à deux axes ne capture pas
 * (écologie, défense, laïcité, institutions) sont classées 'aucun' :
 * exclues du calcul des axes, mais toujours pleinement utilisées pour le
 * matching.
 */

return [
    'themes' => [
        [
            'name' => 'Économie',
            'description' => 'Cette thématique reste la plus discriminante du paysage politique belge : elle oppose la logique de redistribution active portée par le PTB et, dans une moindre mesure, le PS, à la modération fiscale défendue par le MR et Les Engagés. Pour de jeunes primo-votants confrontés au coût de la vie, ces enjeux sont concrets et immédiats.',
        ],
        [
            'name' => 'Environnement',
            'description' => "Le nucléaire demeure un marqueur idéologique fort depuis l'accord de gouvernement Arizona 2025-2029, qui a scellé la prolongation du parc existant. Cette thématique reste par ailleurs la préoccupation générationnelle numéro un chez les jeunes Belges, ce qui justifie sa place centrale dans le questionnaire.",
        ],
        [
            'name' => 'Social',
            'description' => "L'accord Arizona a introduit des réformes substantielles sur le chômage et les pensions, ce qui rend les positions des partis sur ces sujets particulièrement récentes, tranchées et bien documentées : un atout pour la crédibilité du questionnaire.",
        ],
        [
            'name' => 'Immigration',
            'description' => "L'immigration demeure la thématique la plus clivante du débat politique belge contemporain, avec un virage migratoire net imprimé par l'accord Arizona. Pour des jeunes vivant dans une société multiculturelle, à Bruxelles en particulier, cette question reste très concrète.",
        ],
        [
            'name' => 'Société',
            'description' => 'Cette thématique couvre la vision de la société, des libertés individuelles et des droits. Elle permet notamment de distinguer des partis économiquement proches mais sociétalement différents, comme le MR et Les Engagés.',
        ],
    ],

    // color_hex : teintes usuelles de communication de chaque parti, à
    // valider avec de vraies chartes graphiques avant mise en production —
    // non fournies par le document expert (hors de son périmètre : contenu
    // politique, pas identité visuelle).
    'parties' => [
        'PS' => [
            'name' => 'PS', 'abbreviation' => 'PS', 'language_community' => 'FR', 'color_hex' => '#E4032E',
            'description' => 'Le Parti Socialiste est l\'un des plus anciens partis belges, fondé en 1885 et historiquement lié au mouvement ouvrier et syndical wallon et bruxellois. Il est aujourd\'hui le principal parti de gauche francophone, défendant un rôle actif de l\'État dans l\'économie, la redistribution des richesses et le renforcement de la sécurité sociale (pensions, chômage, soins de santé). Il accorde une place centrale au pouvoir d\'achat des travailleurs et des pensionnés, à l\'accès au logement et aux services publics, tout en restant très attentif aux enjeux liés à l\'égalité des droits en Wallonie et à Bruxelles.',
            'slogan' => 'Solide et solidaire',
        ],
        'MR' => [
            'name' => 'MR', 'abbreviation' => 'MR', 'language_community' => 'FR', 'color_hex' => '#0055A4',
            'description' => 'Le Mouvement Réformateur est issu, en 2002, du rassemblement du Parti Réformateur Libéral (PRL) et d\'autres formations centristes et libérales francophones, dans une tradition libérale belge qui remonte au 19e siècle. Il se positionne à droite sur le plan économique, en défendant la modération fiscale, la responsabilisation individuelle et une intervention limitée de l\'État dans le marché. Ses priorités actuelles portent notamment sur la réforme du marché du travail et de la sécurité sociale, la sécurité et la lutte contre la criminalité, ainsi que le maintien de la fiscalité des entreprises à un niveau compétitif.',
            'slogan' => 'L\'avenir s\'éclaire',
        ],
        'ECOLO' => [
            'name' => 'Écolo', 'abbreviation' => 'ECOLO', 'language_community' => 'FR', 'color_hex' => '#4C9A2A',
            'description' => 'Écolo est le parti écologiste francophone belge, fondé en 1980, l\'un des tout premiers partis verts d\'Europe à avoir siégé dans un Parlement national. Il place la transition environnementale et climatique au cœur de son projet politique, tout en défendant des positions progressistes sur les questions de société (droits des minorités, égalité de genre, diversité). Ses priorités actuelles concernent la sortie du nucléaire, le développement des transports en commun et de la mobilité douce, ainsi que la justice sociale et fiscale liée à la transition écologique.',
            'slogan' => 'Choisir l\'avenir (plus vert et plus juste)',
        ],
        'PTB' => [
            'name' => 'PTB', 'abbreviation' => 'PTB', 'language_community' => 'FR', 'color_hex' => '#B0212F',
            'description' => 'Le Parti du Travail de Belgique est un parti marxiste fondé en 1979, issu de mouvements maoïstes des années 1970, qui s\'est progressivement structuré en parti électoral à partir des années 2000. Il se situe à la gauche radicale de l\'échiquier politique belge et défend une redistribution beaucoup plus importante des richesses, un rôle fort de l\'État dans l\'économie et la remise en cause de certaines réformes récentes du marché du travail. Ses priorités actuelles portent sur le pouvoir d\'achat et le salaire minimum, la gratuité de certains services publics (soins de santé, transports, enseignement) et la taxation accrue des grandes fortunes et des multinationales.',
            'slogan' => 'Le choix de la rupture',
        ],
        'DEFI' => [
            'name' => 'DéFI', 'abbreviation' => 'DEFI', 'language_community' => 'FR', 'color_hex' => '#F58220',
            'description' => 'DéFI (Démocrate Fédéraliste Indépendant) est un parti francophone fondé en 1964 sous le nom de Front démocratique des francophones (FDF), à l\'origine pour défendre les intérêts des francophones à Bruxelles et dans sa périphérie face aux revendications flamandes. Il occupe aujourd\'hui une position centriste, combinant des positions économiquement plutôt libérales avec un fédéralisme affirmé et un attachement particulier à Bruxelles. Ses priorités actuelles concernent la réforme de la fiscalité sur le travail, la sécurité et la lutte contre le narcotrafic, ainsi que la défense du fait francophone dans les institutions belges.',
            'slogan' => 'Volonté et Justice',
        ],
        'ENGAGES' => [
            'name' => 'Les Engagés', 'abbreviation' => 'ENGAGES', 'language_community' => 'FR', 'color_hex' => '#FF6B4A',
            'description' => 'Les Engagés sont l\'héritier direct du Centre Démocrate Humaniste (cdH), lui-même issu du Parti Social Chrétien (PSC), et se sont rebaptisés et relancés en 2022 sous cette nouvelle identité. Le parti se positionne au centre de l\'échiquier politique, en s\'appuyant sur des valeurs humanistes issues du personnalisme chrétien, avec une approche qui allie prudence budgétaire et attention aux solidarités sociales. Ses priorités actuelles portent sur le redressement des finances publiques, une réforme en profondeur des pensions et du marché du travail, ainsi que la valorisation de la famille et de l\'enseignement.',
            'slogan' => 'Le courage de changer',
        ],
    ],

    'questions' => [
        // ── THÉMATIQUE 1 — ÉCONOMIE & POUVOIR D'ACHAT ──────────────────────
        [
            'theme' => 'Économie', 'weight' => 3, 'axe_ideologique' => 'economique',
            'label' => 'Le salaire minimum en Belgique devrait être significativement augmenté pour permettre à chacun de vivre dignement.',
            'explanation' => "En Belgique, le salaire minimum brut mensuel s'élève actuellement à un peu plus de 2 070 euros. Le débat porte sur le fait de savoir si ce montant permet réellement de couvrir les dépenses essentielles (logement, énergie, alimentation) face à la hausse du coût de la vie, et si une hausse plus forte pourrait avoir un impact sur les prix ou l'emploi.",
            'positions' => [
                'PS' => [2, "Le PS propose de porter le salaire minimum à 2.800 euros brut par mois (17 euros/heure), contre environ 2.070 euros aujourd'hui, une hausse significative au-delà de la seule indexation.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR ne porte pas de mesure de hausse du salaire minimum dans son programme 2024 ; il privilégie une baisse de la fiscalité sur le travail (quotité exonérée relevée au niveau du revenu d'intégration sociale) plutôt qu'une hausse du salaire brut.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [1, "Écolo veut porter le salaire minimum à 60% du salaire médian, conformément à la directive européenne, complété par un crédit d'impôt solidaire pouvant aller jusqu'à 300-350 euros nets/mois pour les bas salaires.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB revendique explicitement un salaire minimum de 2.800 euros brut par mois, assorti d'une semaine de 30 heures sans perte de salaire.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI fixe un salaire minimum cible de 1.800 euros net associé à un 'bouclier social' de 1.300 euros, une position plus modérée qui ne mise pas sur une hausse spectaculaire du brut.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés misent sur un 'bonus bosseur' de 450 euros nets et une hausse de la quotité exonérée d'impôt plutôt que sur une augmentation directe du salaire minimum brut.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Économie', 'weight' => 3, 'axe_ideologique' => 'economique',
            'label' => 'Les grandes fortunes et les patrimoines élevés devraient être davantage taxés en Belgique.',
            'explanation' => "Une partie du patrimoine des ménages (immobilier, placements financiers, épargne) est aujourd'hui taxée de façon différente selon sa nature, et la Belgique ne possède pas d'impôt général sur les grandes fortunes. Le débat porte sur l'idée de créer une taxe spécifique sur les patrimoines les plus élevés, afin de financer les dépenses publiques, ce qui soulève des questions sur son efficacité et son impact économique.",
            'positions' => [
                'PS' => [2, "Le PS défend depuis 2015 une proposition de loi instaurant un impôt progressif sur les patrimoines nets supérieurs à 1,25 million d'euros (hors habitation propre), avec des taux allant de 0,40% à 1,50%.", 'Proposition de loi PS (depuis 2015)'],
                'MR' => [-2, "Le président du MR s'est exprimé contre tout impôt sur la fortune, estimant que la classe moyenne est déjà trop taxée ; cette mesure est absente du programme 2024 du parti.", 'Déclaration officielle du président du MR'],
                'ECOLO' => [2, "Écolo propose une contribution annuelle sur les patrimoines nets supérieurs à 1 million d'euros (hors habitation principale et outil professionnel), avec un barème progressif jusqu'à 1,5% pour les plus de 10 millions.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB revendique une 'taxe des millionnaires' ciblant les fortunes nettes supérieures à 5 millions d'euros, visant explicitement le 1% le plus riche de la population.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [-1, "DéFI ne porte pas de proposition d'impôt sur la fortune dans son programme 2024 ; le parti n'y est pas opposé par principe mais n'en est pas demandeur, préférant réformer l'impôt des personnes physiques.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés ne proposent pas d'impôt sur la fortune ; leur 'Plan stratégique Fiscalité et Travail' privilégie une baisse de la fiscalité sur le travail plutôt qu'une nouvelle taxation du capital.", 'Plan stratégique Fiscalité et Travail (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Économie', 'weight' => 3, 'axe_ideologique' => 'economique',
            'label' => "Les allocations de chômage devraient être limitées dans le temps pour encourager le retour à l'emploi.",
            'explanation' => "Depuis une réforme entrée en vigueur en 2025, la durée pendant laquelle une personne peut toucher une allocation de chômage en Belgique est plafonnée à deux ans maximum, alors qu'elle pouvait auparavant être perçue sans limite de durée dans certains cas. Le débat porte sur l'équilibre entre inciter les personnes sans emploi à retrouver rapidement du travail et leur laisser le temps nécessaire pour trouver un emploi adapté à leur situation.",
            'positions' => [
                'PS' => [-2, "Le PS s'oppose à la limitation dans le temps des allocations de chômage, privilégiant un accompagnement vers l'emploi plutôt que la sanction ; il a combattu cette mesure au sein de l'accord Arizona 2025-2029.", "Position du PS lors des négociations de l'accord Arizona 2025-2029"],
                'MR' => [2, "Le MR a obtenu dans l'accord de gouvernement Arizona 2025-2029 la limitation des allocations de chômage à un maximum de deux ans à partir du 1er janvier 2026, mesure phare de son programme 2024.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [-2, "Écolo n'a jamais soutenu de limitation dans le temps des allocations de chômage et a dénoncé cette mesure lors de sa mise en œuvre dans l'accord Arizona.", "Position publique d'Écolo sur l'accord Arizona"],
                'PTB' => [-2, "Le PTB s'oppose frontalement à la limitation des allocations de chômage inscrite dans l'accord Arizona 2025-2029, qu'il juge socialement brutale.", "Position publique du PTB sur l'accord Arizona"],
                'DEFI' => [1, "DéFI n'a pas porté cette mesure comme axe central de campagne mais ne s'y est pas non plus opposé fermement, sa ligne restant proche d'une activation renforcée des chercheurs d'emploi.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [2, "Les Engagés ont co-négocié et cosigné l'accord de gouvernement Arizona 2025-2029, qui instaure la limitation à deux ans des allocations de chômage.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Économie', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => "L'État devrait jouer un rôle plus important dans la régulation des prix de l'énergie et des produits de première nécessité.",
            'explanation' => "Les prix de l'énergie (gaz, électricité) et de certains produits essentiels sont en grande partie fixés par le marché, avec des mécanismes de soutien public ponctuels en cas de crise (comme lors de la hausse des prix de l'énergie en 2022). Le débat porte sur le degré d'intervention souhaitable de l'État pour encadrer ces prix, entre laisser faire la concurrence et fixer des plafonds ou des tarifs sociaux.",
            'positions' => [
                'PS' => [2, "Le PS défend une régulation active des prix de l'énergie et des produits de première nécessité, dans la ligne de sa demande historique de plafonnement des prix de l'énergie.", 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR privilégie la concurrence et la libéralisation du marché plutôt que l'intervention étatique sur les prix, qu'il juge contre-productive pour l'investissement énergétique.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [1, "Écolo soutient un encadrement des prix de l'énergie, notamment via la redistribution des recettes du signal-prix carbone (chèque-planète), tout en restant favorable aux mécanismes de marché régulés.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB revendique la nationalisation du secteur énergétique (Engie, Electrabel) pour permettre à l'État de maîtriser directement les prix.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI ne place pas la régulation étatique des prix au centre de son programme, sans s'y opposer explicitement dans des cas ciblés.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés privilégient des mécanismes de soutien ciblé (bonus, crédits d'impôt) plutôt qu'une régulation directe des prix par l'État.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Économie', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => "Les entreprises belges paient suffisamment d'impôts sur leurs bénéfices.",
            'explanation' => "L'impôt des sociétés en Belgique est fixé à un taux nominal de 25 %, mais de nombreuses entreprises, notamment les multinationales, bénéficient de mécanismes de déduction qui réduisent le montant effectivement payé. Le débat porte sur le point de savoir si le niveau réel de taxation des bénéfices des entreprises est suffisant ou trop bas par rapport à leur capacité contributive.",
            'positions' => [
                'PS' => [-2, "Le PS estime que les entreprises, en particulier les multinationales, ne paient pas suffisamment d'impôts et propose une 'contribution sur les bénéfices excessifs' ainsi qu'un renforcement de l'impôt minimum mondial à 15%, voire davantage.", 'Programme électoral 2024 (PS)'],
                'MR' => [2, "Le MR juge la fiscalité des entreprises déjà élevée et propose de réduire l'impôt des sociétés à 15% pour les PME, estimant que la compétitivité fiscale doit être renforcée.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [-2, 'Écolo considère la fiscalité des entreprises insuffisamment juste et veut un taux minimum effectif international de 25% pour les multinationales.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [-2, 'Le PTB dénonce une fiscalité des entreprises trop clémente et réclame une taxation accrue des bénéfices et du capital.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, 'DéFI ne prend pas position tranchée sur le niveau global de taxation des entreprises, se concentrant sur des mesures ciblées pour les indépendants et starters.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [1, "Les Engagés estiment la fiscalité des entreprises globalement adéquate et proposent plutôt des moratoires fiscaux pour les jeunes PME, sans réclamer une hausse de l'impôt des sociétés.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Économie', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => "L'indexation automatique des salaires, qui adapte les salaires à l'inflation, doit être préservée sans exception.",
            'explanation' => "En Belgique, la plupart des salaires et des allocations sociales sont automatiquement adaptés à l'évolution des prix grâce à un mécanisme appelé indexation : quand le coût de la vie augmente d'un certain pourcentage, les revenus augmentent dans la même proportion. Ce système, plutôt rare en Europe, protège le pouvoir d'achat des travailleurs, mais certains estiment qu'il pèse sur la compétitivité des entreprises belges.",
            'positions' => [
                'PS' => [2, "Le PS défend l'indexation automatique des salaires 'sans exception' comme un pilier intouchable du pouvoir d'achat des travailleurs belges.", 'Programme électoral 2024 (PS)'],
                'MR' => [0, "Le MR ne remet pas en cause l'indexation automatique mais refuse également de l'étendre ; il maintient un équilibre entre norme salariale stricte et indexation existante, sans vouloir y toucher ni la renforcer.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo veut garantir et améliorer le système d'indexation automatique, notamment en le généralisant à l'ensemble des travailleurs et en harmonisant son calendrier.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB défend fermement le maintien intégral de l'indexation automatique des salaires, sans aucune exception ni saut d'index.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [1, "DéFI ne conteste pas le principe de l'indexation mais reste discret sur le sujet dans son programme, sans en faire un combat prioritaire.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés n'ont pas remis en cause le mécanisme dans leur programme 2024, mais l'accord Arizona qu'ils ont cosigné prévoit des sauts d'index partiels pour certaines catégories, ce qui nuance leur position.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],

        // ── THÉMATIQUE 2 — ENVIRONNEMENT & ÉNERGIE ──────────────────────────
        [
            'theme' => 'Environnement', 'weight' => 3, 'axe_ideologique' => 'aucun',
            'label' => "La Belgique devrait sortir définitivement de l'énergie nucléaire dans les prochaines années.",
            'explanation' => "La Belgique produit une part importante de son électricité grâce à des centrales nucléaires situées à Doel et à Tihange, dont les réacteurs les plus anciens ont été mis en service entre 1975 et 1985, soit il y a 40 à 50 ans. Le débat porte sur la sécurité de ces installations vieillissantes, le coût de leur remplacement par d'autres sources d'énergie, et l'indépendance énergétique du pays face aux importations.",
            'positions' => [
                'PS' => [0, "Le PS a tenu des positions contradictoires sur le nucléaire : il est demandeur d'une prolongation de Doel 4 et Tihange 3 au-delà de 10 ans tout en visant un scénario 100% renouvelable à l'horizon 2050.", 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR demande la prolongation de l'ensemble du parc nucléaire existant, la construction de nouvelles centrales pour 8 GW et le développement de petits réacteurs modulaires (SMR) ; c'est l'un des partis les plus pro-nucléaires.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo ambitionne toujours de sortir complètement du nucléaire dès 2035, malgré avoir dû accepter la prolongation de deux réacteurs sous la précédente législature ; c'est le seul grand parti francophone à porter une sortie ferme.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [0, "Le PTB juge le nucléaire 'trop cher et trop lent' à développer mais n'exclut pas de devoir compter sur les réacteurs existants encore un certain temps ; sa position est nuancée, ni pro-sortie ferme ni pro-relance.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [-2, 'DéFI plaide pour prolonger les réacteurs les plus récents (Doel 4, Tihange 3) pour 20 ans, réétudier Doel 3 et Tihange 2, et construire de nouvelles centrales classiques dès 2035.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-2, 'Les Engagés veulent investir dans le nucléaire de nouvelle génération et prolonger les deux réacteurs les plus récents pour 20 ans et les cinq autres pour 10 ans.', 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Environnement', 'weight' => 2, 'axe_ideologique' => 'aucun',
            'label' => "La Belgique devrait investir massivement dans les transports publics pour réduire l'usage de la voiture individuelle.",
            'explanation' => "Les transports en commun (bus, trams, trains) sont aujourd'hui payants dans la quasi-totalité de la Belgique, avec des réductions pour certains publics comme les étudiants. Le débat porte sur l'intérêt d'investir massivement dans ce réseau (fréquence, gratuité, nouvelles lignes) pour réduire l'usage de la voiture individuelle, sachant que cela nécessite un financement public important.",
            'positions' => [
                'PS' => [2, "Le PS défend la gratuité totale des transports en commun, avec une première étape ciblée sur les moins de 25 ans, les plus de 65 ans et les bénéficiaires de l'intervention majorée, et le bouclage du métro 3 à Bruxelles.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR préconise une approche ciblée plutôt qu'une gratuité généralisée, privilégiant les étudiants qui réussissent leurs études et les chercheurs d'emploi en formation.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, 'Écolo veut étendre la gratuité ciblée des transports en commun à la SNCB, financée par un transfert des budgets alloués aux voitures de société et à la carte essence.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB plaide pour la gratuité totale des transports en commun (TEC et Stib comme première étape), financée par une cotisation transport des entreprises de plus de 20 travailleurs.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [-1, "DéFI n'est pas favorable à une gratuité généralisée, préférant investir dans le confort, la sécurité et les fréquences des transports existants.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés veulent étendre les tarifs réduits existants (12-26 ans) sous condition d'évaluation positive, une position modérée sans gratuité généralisée.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Environnement', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => "Les entreprises qui émettent le plus d'émissions polluantes devraient contribuer davantage au financement de la transition écologique.",
            'explanation' => "Certaines entreprises paient déjà des taxes liées à leurs émissions polluantes, notamment via le système européen d'échange de quotas de carbone, mais le niveau de cette contribution reste débattu. La question porte sur le fait de savoir si les entreprises les plus polluantes devraient payer davantage pour financer la transition vers une économie plus respectueuse de l'environnement, sans que cela ne pénalise excessivement leur activité ou les emplois qui en dépendent.",
            'positions' => [
                'PS' => [1, "Le PS s'est positionné pour une TVA plus élevée sur les produits nuisibles à l'environnement lors du test électoral RTBF 2024, tout en veillant à ne pas pénaliser le pouvoir d'achat des travailleurs.", 'Test électoral RTBF 2024'],
                'MR' => [-2, "Le MR s'est positionné contre une taxation environnementale accrue des entreprises et n'est pas favorable au budget carbone obligatoire, privilégiant les incitants fiscaux plutôt que la taxation punitive.", 'Test électoral RTBF 2024'],
                'ECOLO' => [2, "Écolo s'est positionné pour une taxation environnementale renforcée des entreprises polluantes et soutient les budgets carbones sectoriels ainsi qu'un mécanisme de pollueur-payeur.", 'Test électoral RTBF 2024'],
                'PTB' => [-1, "Le PTB s'oppose à la taxe carbone actuelle qu'il juge injuste pour les ménages, mais réclame en contrepartie des normes environnementales contraignantes imposées directement aux entreprises plutôt qu'une taxe.", 'Position publique du PTB'],
                'DEFI' => [1, "DéFI s'est positionné pour une taxation environnementale accrue lors du test électoral RTBF 2024, tout en restant réservé sur les modalités de mise en œuvre.", 'Test électoral RTBF 2024'],
                'ENGAGES' => [2, "Les Engagés se sont positionnés pour une TVA plus élevée sur les produits polluants et soutiennent le mécanisme européen d'ajustement carbone aux frontières.", 'Test électoral RTBF 2024'],
            ],
        ],
        [
            'theme' => 'Environnement', 'weight' => 1, 'axe_ideologique' => 'aucun',
            'label' => 'La rénovation énergétique des logements devrait être rendue obligatoire et financée en partie par les pouvoirs publics.',
            'explanation' => "Une partie du parc immobilier belge est ancienne et mal isolée, ce qui entraîne une consommation d'énergie élevée pour le chauffage. Le débat porte sur le fait de rendre obligatoire la rénovation énergétique des logements (isolation, chauffage) plutôt que de la laisser au choix des propriétaires, ainsi que sur le rôle des pouvoirs publics dans le financement de ces travaux souvent coûteux.",
            'positions' => [
                'PS' => [2, 'Le PS soutient un plan de rénovation énergétique des logements financé en partie par les pouvoirs publics, en particulier pour les ménages précarisés.', 'Programme électoral 2024 (PS)'],
                'MR' => [-1, 'Le MR privilégie des incitants fiscaux (primes, réductions) à toute obligation de rénovation énergétique, jugée trop contraignante pour les propriétaires.', 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo défend une obligation progressive de rénovation énergétique des logements, assortie d'un soutien financier public pour les ménages à revenus modestes.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [1, "Le PTB soutient un plan d'investissement public ambitieux dans la rénovation des logements, sans nécessairement imposer une obligation individuelle stricte aux propriétaires modestes.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI n'a pas de position tranchée sur l'obligation de rénovation énergétique dans son programme 2024.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, 'Les Engagés soutiennent des primes à la rénovation mais restent prudents sur le caractère obligatoire, en particulier pour les ménages ruraux et modestes.', 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Environnement', 'weight' => 1, 'axe_ideologique' => 'aucun',
            'label' => 'La protection de la biodiversité et des espaces naturels doit primer sur les projets économiques et immobiliers.',
            'explanation' => "La Belgique est l'un des pays européens où la nature occupe une part relativement faible du territoire, largement urbanisé et agricole. Le débat porte sur la priorité à donner, en cas de conflit d'usage du sol, entre la préservation des espaces naturels et de la biodiversité, et la réalisation de projets économiques ou immobiliers (logements, zones industrielles, infrastructures).",
            'positions' => [
                'PS' => [1, "Le PS obtient un score de 7/10 dans l'évaluation de Protection des Oiseaux de Belgique sur les mesures de conservation de la nature, plaidant pour un équilibre entre préservation et développement.", 'Évaluation Protection des Oiseaux de Belgique'],
                'MR' => [-1, "Le MR obtient un score de 5/10 sur les mesures de conservation de la nature et refuse d'imposer des obligations agro-environnementales strictes, privilégiant les intérêts économiques et les propriétaires concernés.", 'Évaluation Protection des Oiseaux de Belgique'],
                'ECOLO' => [2, 'Écolo obtient le score maximal (10/10) sur les mesures de conservation de la nature, plaidant pour que la protection de la biodiversité prime sur les projets économiques et immobiliers.', 'Évaluation Protection des Oiseaux de Belgique'],
                'PTB' => [1, 'Le PTB obtient un score de 8/10 sur les mesures de conservation de la nature, favorable à des mesures agro-environnementales contraignantes.', 'Évaluation Protection des Oiseaux de Belgique'],
                'DEFI' => [2, 'DéFI obtient le score maximal (10/10) sur les mesures de conservation de la nature, à égalité avec Écolo.', 'Évaluation Protection des Oiseaux de Belgique'],
                'ENGAGES' => [-1, 'Les Engagés (héritiers du cdH) plaident pour tenir compte des intérêts des propriétaires et des zones rurales dans toute réglementation territoriale, une ligne plus mesurée sur la priorité à donner à la biodiversité.', 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Environnement', 'weight' => 2, 'axe_ideologique' => 'aucun',
            'label' => 'La Belgique devrait adopter des objectifs climatiques plus ambitieux, même si cela implique des contraintes économiques à court terme.',
            'explanation' => "La Belgique s'est engagée, comme les autres pays de l'Union européenne, à réduire ses émissions de gaz à effet de serre selon un calendrier précis, dans le cadre du Pacte vert européen (Green Deal). Le débat porte sur le rythme de cette transition : faut-il aller plus loin et plus vite dans les objectifs climatiques, même si cela implique des coûts ou des contraintes pour les entreprises et les ménages à court terme.",
            'positions' => [
                'PS' => [1, "Le PS soutient une application harmonisée et ambitieuse du Green Deal européen et s'oppose à toute pause réglementaire climatique.", 'Position publique du PS sur le Green Deal'],
                'MR' => [-2, "Le MR demande d'évaluer et de simplifier les réglementations climatiques en fonction de leur efficacité et de la compétitivité économique, plutôt que de fixer des objectifs plus stricts sans égard aux contraintes.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, 'Écolo refuse catégoriquement toute pause dans la mise en œuvre du Green Deal et défend des objectifs climatiques renforcés, y compris au prix de contraintes économiques à court terme.', "Position publique d'Écolo sur le Green Deal"],
                'PTB' => [0, "Le PTB soutient des objectifs climatiques ambitieux mais conditionne leur financement à une contribution des grandes fortunes et des pollueurs plutôt qu'aux ménages, une position nuancée sur l'acceptation de contraintes économiques générales.", 'Position publique du PTB'],
                'DEFI' => [2, "DéFI s'oppose à toute pause réglementaire dans la mise en œuvre du Green Deal et soutient une application harmonisée des objectifs climatiques européens.", 'Position publique de DéFI sur le Green Deal'],
                'ENGAGES' => [2, 'Les Engagés refusent toute pause environnementale ou réglementaire dans la mise en œuvre du Green Deal, se disant fiers de son portage par leur famille politique européenne.', 'Position publique des Engagés sur le Green Deal'],
            ],
        ],

        // ── THÉMATIQUE 3 — SOCIAL & PROTECTION SOCIALE ──────────────────────
        [
            'theme' => 'Social', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => "L'accès à un logement abordable est un droit fondamental que l'État doit garantir activement.",
            'explanation' => "Le prix des loyers et des logements a fortement augmenté ces dernières années en Belgique, en particulier à Bruxelles et dans les grandes villes, tandis que le nombre de logements sociaux disponibles reste limité par rapport à la demande. Le débat porte sur le rôle que l'État doit jouer pour garantir un accès au logement à un prix abordable, par exemple en encadrant les loyers ou en construisant davantage de logements publics.",
            'positions' => [
                'PS' => [2, "Le PS défend le logement comme un droit fondamental et propose un renforcement des logements publics et sociaux ainsi qu'un encadrement des loyers.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR privilégie le soutien à l'accession à la propriété (réduction des droits d'enregistrement, suppression progressive du précompte immobilier) plutôt qu'une garantie étatique directe du logement.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, 'Écolo considère le logement comme un droit fondamental à garantir activement, avec un encadrement des loyers et un renforcement du parc de logements publics.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB défend fermement le logement comme un droit fondamental, plaidant pour un blocage des loyers et une extension massive du logement public.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [1, "DéFI soutient un renforcement de l'accès au logement via l'encadrement d'Airbnb et le soutien aux agences immobilières sociales, sans toutefois porter une garantie étatique aussi large que la gauche.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés soutiennent des mesures d'aide au logement mais restent plus mesurés sur une garantie étatique généralisée, préférant des incitants ciblés.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Social', 'weight' => 3, 'axe_ideologique' => 'economique',
            'label' => "L'âge légal de la retraite ne devrait pas dépasser 65 ans, quelle que soit la durée de carrière.",
            'explanation' => "L'âge légal de la pension en Belgique, actuellement fixé à 66 ans, doit progressivement passer à 67 ans à partir de 2030. Le débat porte sur le fait de savoir si cet âge devrait rester plafonné à 65 ans, quelle que soit la durée de la carrière professionnelle, ou si le report à un âge plus élevé est nécessaire pour financer le système des pensions sur le long terme.",
            'positions' => [
                'PS' => [1, "Le PS veut permettre un départ à la retraite dès 60 ans pour 42 années de carrière et ne défend pas un relèvement au-delà de l'âge légal actuel, restant proche d'un plafond autour de 65 ans pour les longues carrières.", 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR ne remet pas en cause l'âge légal de la pension à 67 ans et privilégie un basculement vers la durée de carrière effective plutôt qu'un âge plafonné à 65 ans.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [1, "Écolo veut fixer l'âge de départ en fonction de la durée et de la pénibilité de la carrière, garantissant un droit au départ après 42 ans de carrière, ce qui se rapproche d'un objectif proche de 65 ans pour la plupart des travailleurs.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB revendique explicitement le retour à la pension légale à 65 ans, jugeant le report à 67 ans 'injuste et irréalisable'.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [-1, "DéFI met l'accent sur la durée de carrière plutôt que sur un âge plafonné, une position proche de celle du MR qui n'exclut pas un départ après 65 ans selon la carrière.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés veulent faire de la durée de carrière le paramètre central plutôt qu'un âge uniforme, permettant un départ plus précoce pour certains sans fixer de plafond général à 65 ans.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Social', 'weight' => 1, 'axe_ideologique' => 'economique',
            'label' => 'Les soins de santé en Belgique devraient être entièrement gratuits pour les personnes à faibles revenus.',
            'explanation' => "En Belgique, les soins de santé sont partiellement remboursés par la sécurité sociale, mais une partie des frais (suppléments d'honoraires, tickets modérateurs) reste à charge du patient, ce qui peut représenter un obstacle pour les personnes à faibles revenus malgré des dispositifs d'aide existants comme l'intervention majorée. Le débat porte sur l'idée d'étendre la gratuité complète des soins pour ces publics, ce qui aurait un coût pour les finances publiques.",
            'positions' => [
                'PS' => [2, "Le PS défend une norme de croissance de 3% dans les soins de santé et l'extension de l'interdiction des suppléments d'honoraires pour améliorer l'accès gratuit aux soins pour les revenus modestes.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR privilégie la maîtrise budgétaire du système de santé et le maintien des mécanismes existants (intervention majorée) plutôt qu'une gratuité étendue des soins.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [1, "Écolo soutient un renforcement de l'accessibilité financière aux soins de santé pour les publics précarisés, sans aller jusqu'à une gratuité totale généralisée dans son programme.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB défend la gratuité des soins de santé de première ligne pour les personnes à faibles revenus et la suppression généralisée des suppléments d'honoraires.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, 'DéFI ne porte pas de proposition de gratuité des soins de santé comme axe central de son programme 2024.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés privilégient la soutenabilité budgétaire du système de santé, en ligne avec les réformes de l'accord Arizona, plutôt qu'une extension de la gratuité.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Social', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => 'Le système actuel de sécurité sociale belge devrait être réformé pour renforcer sa soutenabilité financière à long terme.',
            'explanation' => "La sécurité sociale belge (pensions, chômage, soins de santé, allocations familiales) est financée par les cotisations des travailleurs et des employeurs, ainsi que par l'État, dans un contexte de vieillissement de la population qui augmente les dépenses. Le débat porte sur la nécessité de réformer ce système pour garantir qu'il reste finançable sur le long terme, et sur les moyens d'y parvenir (économies, nouvelles recettes, ou les deux).",
            'positions' => [
                'PS' => [-2, "Le PS s'oppose fermement au discours d'une sécurité sociale 'trop généreuse' et a combattu les réformes de limitation du chômage et des pensions inscrites dans l'accord Arizona.", "Position du PS lors des négociations de l'accord Arizona 2025-2029"],
                'MR' => [2, "Le MR défend depuis des années l'idée que la sécurité sociale doit être réformée pour rester soutenable, ce qui s'est traduit par la limitation des allocations de chômage et la réforme des pensions dans l'accord Arizona 2025-2029.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [-2, "Écolo rejette l'idée d'une sécurité sociale trop généreuse et a dénoncé les coupes opérées par le gouvernement Arizona dans le chômage et les pensions.", "Position publique d'Écolo sur l'accord Arizona"],
                'PTB' => [-2, "Le PTB rejette catégoriquement le discours de la sécurité sociale 'trop généreuse', qu'il considère comme un prétexte pour justifier des coupes sociales.", 'Position publique du PTB'],
                'DEFI' => [0, "DéFI adopte une position d'équilibre, sans reprendre le discours d'une sécurité sociale trop généreuse mais sans non plus s'opposer à toute réforme de soutenabilité.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [1, "Les Engagés ont cosigné l'accord Arizona qui réforme en profondeur pensions et chômage au nom de la soutenabilité financière, tout en défendant des mesures de solidarité comme la pension minimale à 1.500 euros.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Social', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => 'Les études supérieures devraient être totalement gratuites en Belgique francophone.',
            'explanation' => "Les frais d'inscription (minerval) dans l'enseignement supérieur en Belgique francophone s'élèvent aujourd'hui à plusieurs centaines d'euros par an pour la majorité des étudiants, un montant plus faible que dans certains pays mais qui peut néanmoins représenter un frein pour certaines familles. Le débat porte sur l'intérêt de supprimer totalement ces frais, ce qui nécessiterait un refinancement important de l'enseignement supérieur par les pouvoirs publics.",
            'positions' => [
                'PS' => [1, "Le PS veut étendre le gel du minerval à l'ensemble des cursus et œuvrer au refinancement de l'enseignement supérieur, sans toutefois promettre une gratuité totale immédiate.", 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR ne porte pas de proposition de gratuité des études supérieures et privilégie d'autres priorités budgétaires pour l'enseignement.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [1, "Écolo soutient le refinancement de l'enseignement supérieur et la réduction des frais d'inscription, sans aller explicitement jusqu'à une gratuité totale généralisée.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB revendique la gratuité totale de l'enseignement supérieur, considérée comme une condition d'égalité d'accès aux études pour tous les jeunes.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [-1, "DéFI n'inscrit pas la gratuité des études supérieures dans son programme, restant centré sur d'autres priorités d'enseignement.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés ne portent pas de proposition de gratuité totale, privilégiant un refinancement ciblé et une diminution progressive des frais d'inscription.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Social', 'weight' => 2, 'axe_ideologique' => 'economique',
            'label' => 'Les syndicats jouent un rôle essentiel dans la défense des travailleurs et leurs droits doivent être renforcés.',
            'explanation' => "Les syndicats représentent les travailleurs dans les négociations avec les employeurs et l'État, notamment sur les salaires et les conditions de travail, et gèrent aussi certaines missions comme le paiement de certaines allocations de chômage. Le débat porte sur le rôle que ces organisations doivent continuer à jouer dans la société belge, notamment à la suite de réformes récentes qui ont modifié leur implication dans la gestion de certaines aides sociales.",
            'positions' => [
                'PS' => [2, 'Le PS, historiquement lié au monde syndical socialiste, défend un renforcement du rôle des syndicats dans la négociation collective et la défense des travailleurs.', 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR a exclu les partenaires sociaux du comité de gestion de la Caisse auxiliaire de paiement des allocations de chômage dans l'accord Arizona, une mesure perçue comme un affaiblissement du rôle syndical.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [1, "Écolo reconnaît le rôle essentiel des syndicats dans la défense des travailleurs, sans en faire l'axe central de son programme économique.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB, très proche du mouvement syndical de gauche, défend un renforcement systématique des droits syndicaux et du pouvoir de négociation collective.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI n'a pas de position tranchée sur le renforcement du rôle syndical dans son programme 2024.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés, en cosignant l'accord Arizona qui réduit le rôle des partenaires sociaux dans la gestion du chômage, ont contribué à un recul institutionnel du poids syndical.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],

        // ── THÉMATIQUE 4 — IMMIGRATION & VIVRE-ENSEMBLE ─────────────────────
        [
            'theme' => 'Immigration', 'weight' => 3, 'axe_ideologique' => 'societal',
            'label' => "La Belgique devrait accueillir davantage de réfugiés et de demandeurs d'asile, en renforçant ses capacités d'accueil.",
            'explanation' => "La Belgique reçoit chaque année plusieurs dizaines de milliers de demandes d'asile, et le réseau d'accueil (Fedasil et ses partenaires) a connu une saturation répétée ces dernières années, avec des personnes parfois laissées sans hébergement malgré des décisions de justice l'imposant. Le débat porte sur le fait d'augmenter ou non les capacités d'accueil du pays pour les réfugiés et demandeurs d'asile.",
            'positions' => [
                'PS' => [1, "Le PS veut un système d'asile efficace et transparent, limitant le recours à la détention en centre fermé, tout en n'inscrivant pas un accueil massivement élargi comme priorité chiffrée.", 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR défend un accueil dans des 'hotspots' aux frontières de l'UE plutôt qu'en Belgique, et a porté au sein de l'accord Arizona 2025-2029 une réduction des places d'accueil et un durcissement généralisé de la politique migratoire.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [2, "Écolo plaide pour un accueil digne et un renforcement des capacités d'accueil, en opposition frontale aux mesures restrictives de l'accord Arizona.", "Position publique d'Écolo sur l'accord Arizona"],
                'PTB' => [2, "Le PTB veut un système de répartition solidaire des demandeurs d'asile à l'échelle européenne et des voies légales sûres pour les réfugiés, s'opposant aux restrictions de l'accord Arizona.", 'Position publique du PTB'],
                'DEFI' => [0, "DéFI n'a pas pris une position tranchée en faveur d'un accueil renforcé dans son programme 2024, occupant une position centriste sur la question migratoire.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés ont cosigné l'accord Arizona qui réduit les places d'accueil et durcit la politique d'asile, tout en affirmant avoir obtenu un traitement digne pour les familles avec enfants mineurs.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Immigration', 'weight' => 3, 'axe_ideologique' => 'societal',
            'label' => 'Les personnes en situation irrégulière qui travaillent depuis plusieurs années et sont bien intégrées devraient pouvoir obtenir un titre de séjour selon des critères légaux clairs.',
            'explanation' => "En Belgique, les personnes qui vivent sans titre de séjour valide, parfois depuis de nombreuses années et en travaillant, ne bénéficient d'aucune procédure automatique de régularisation ; chaque cas est examiné individuellement par l'Office des étrangers selon des critères qui ne sont pas fixés dans une loi. Le débat porte sur l'intérêt d'inscrire dans la loi des critères clairs et objectifs qui permettraient une régularisation prévisible pour certaines situations.",
            'positions' => [
                'PS' => [2, 'Le PS veut fixer des critères clairs, objectifs et permanents de régularisation individuelle des sans-papiers, inscrits dans la loi.', 'Programme électoral 2024 (PS)'],
                'MR' => [-2, "Le MR privilégie le renforcement de la lutte contre l'immigration illégale et l'augmentation des places en centres fermés plutôt que la régularisation, en cohérence avec le durcissement porté dans l'accord Arizona.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [2, "Écolo veut engager un processus de régularisation des sans-papiers sur base de critères objectifs fixés dans la loi, incluant le fait d'avoir un travail ou une promesse d'embauche.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB soutient la régularisation des personnes en situation irrégulière qui travaillent et sont intégrées, dans une logique de lutte contre le dumping social.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI ne porte pas de proposition claire de régularisation dans son programme 2024, occupant une position d'équilibre.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [-1, "Les Engagés, en cosignant l'accord Arizona axé sur la fermeté migratoire, ne portent pas de politique de régularisation étendue des personnes en séjour irrégulier.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Immigration', 'weight' => 2, 'axe_ideologique' => 'societal',
            'label' => 'Les frontières européennes doivent être renforcées pour mieux contrôler les flux migratoires.',
            'explanation' => "L'espace Schengen permet la libre circulation des personnes entre la plupart des pays européens, tout en maintenant un contrôle commun aux frontières extérieures de l'Union européenne, notamment en Méditerranée. Le débat porte sur le fait de renforcer ou non ces contrôles frontaliers extérieurs pour mieux réguler l'arrivée de migrants en Europe, y compris en Belgique.",
            'positions' => [
                'PS' => [-1, "Le PS ne place pas le renforcement des frontières au centre de son programme migratoire, privilégiant l'efficacité des procédures d'asile plutôt qu'un contrôle frontalier accru.", 'Programme électoral 2024 (PS)'],
                'MR' => [2, "Le MR défend la création de 'hotspots' aux frontières de l'UE, le développement des accords de réadmission avec les pays tiers et un contrôle renforcé, en cohérence avec le virage sécuritaire de l'accord Arizona.", 'Accord de gouvernement Arizona 2025-2029'],
                'ECOLO' => [-2, "Écolo s'oppose à une politique de fermeture des frontières et défend au contraire un accueil digne et des voies légales de migration.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [-1, "Le PTB privilégie une répartition solidaire des demandeurs d'asile en Europe plutôt qu'un renforcement des frontières extérieures.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, 'DéFI ne prend pas de position tranchée sur le renforcement des frontières européennes dans son programme.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [1, "Les Engagés ont cosigné l'accord Arizona qui promeut un 'Pacte migratoire européen plus sévère' et un contrôle renforcé aux frontières, tout en défendant une approche alliant fermeté et humanité.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Immigration', 'weight' => 2, 'axe_ideologique' => 'societal',
            'label' => "L'intégration des personnes immigrées doit être une priorité politique, avec des investissements publics importants en formation et en accompagnement.",
            'explanation' => "L'intégration des personnes immigrées passe notamment par l'apprentissage de la langue, la reconnaissance des diplômes étrangers et l'accès à la formation professionnelle, des démarches qui peuvent être longues et complexes. Le débat porte sur le niveau d'investissement public que la Belgique devrait consacrer à ces dispositifs d'accompagnement pour faciliter cette intégration.",
            'positions' => [
                'PS' => [2, "Le PS soutient un investissement public renforcé dans l'intégration des personnes immigrées, notamment via l'octroi de titres de séjour temporaires et un accompagnement social ciblé.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR conditionne l'aide sociale à un parcours d'intégration renforcé et prévoit un délai d'attente de cinq ans avant l'accès à l'aide sociale pour les primo-arrivants, réduisant l'investissement public immédiat en la matière.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo défend un investissement public accru dans l'intégration, notamment via le financement de parcours d'accompagnement et la suppression de redevances administratives pour les demandes de régularisation.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB soutient un investissement public fort dans l'intégration des personnes immigrées et rejette les politiques d'exclusion sociale liées au statut migratoire.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI ne développe pas de proposition centrale sur l'investissement public dans l'intégration des immigrés dans son programme 2024.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés défendent un accueil digne pour les publics vulnérables dans l'accord Arizona, tout en acceptant un conditionnement accru de l'aide sociale à l'intégration civique.", 'Accord de gouvernement Arizona 2025-2029'],
            ],
        ],
        [
            'theme' => 'Immigration', 'weight' => 1, 'axe_ideologique' => 'societal',
            'label' => 'La diversité culturelle est une richesse pour la société belge et doit être valorisée par les pouvoirs publics.',
            'explanation' => "La Belgique, et Bruxelles en particulier, compte une population issue de nombreuses origines et cultures différentes, un phénomène renforcé par plusieurs décennies de migration économique et de demandes d'asile. Le débat porte sur la manière de considérer cette diversité culturelle dans les politiques publiques, entre la valoriser activement comme un atout de la société belge ou rester plus neutre à ce sujet.",
            'positions' => [
                'PS' => [2, 'Le PS valorise la diversité culturelle comme une richesse pour la société belge et défend un renforcement de la lutte contre les discriminations.', 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR met davantage l'accent sur l'assimilation aux valeurs communes et la neutralité de l'État que sur la valorisation de la diversité culturelle comme telle.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, 'Écolo valorise explicitement la diversité culturelle et défend une société inclusive et pluraliste comme un atout pour la Belgique.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [1, 'Le PTB défend une approche inclusive de la diversité culturelle, tout en la reliant surtout à la lutte contre les discriminations sociales et économiques.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, 'DéFI adopte une position plus neutre, insistant davantage sur le vivre-ensemble encadré par la laïcité que sur la valorisation de la diversité en tant que telle.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés reconnaissent la diversité de la société belge sans en faire un thème central positif de leur programme, insistant plutôt sur l'intégration civique.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Immigration', 'weight' => 2, 'axe_ideologique' => 'societal',
            'label' => 'Les personnes étrangères en situation régulière devraient avoir le droit de vote aux élections communales.',
            'explanation' => "Actuellement, les personnes de nationalité étrangère résidant légalement en Belgique ne peuvent pas voter aux élections communales, sauf les citoyens d'un autre pays de l'Union européenne, qui bénéficient de ce droit sous certaines conditions. Le débat porte sur l'extension éventuelle de ce droit de vote local à l'ensemble des étrangers en séjour régulier, quelle que soit leur nationalité.",
            'positions' => [
                'PS' => [1, "Le PS soutient le maintien et l'élargissement du droit de vote communal pour les étrangers en séjour légal, dans une logique d'intégration citoyenne.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR est traditionnellement réservé sur l'extension du droit de vote aux étrangers non-européens, insistant davantage sur les conditions d'intégration préalables.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo défend activement l'élargissement du droit de vote communal aux étrangers en séjour légal comme un vecteur d'intégration démocratique.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [1, "Le PTB soutient le droit de vote des étrangers en séjour légal aux élections communales dans une logique d'égalité citoyenne.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [0, "DéFI n'a pas pris de position tranchée et constante sur l'extension du droit de vote communal aux étrangers dans son programme récent.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés n'ont pas fait de l'extension du droit de vote communal aux étrangers un axe de leur programme 2024, restant discrets sur le sujet.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],

        // ── THÉMATIQUE 5 — DÉMOCRATIE, DROITS & SOCIÉTÉ ─────────────────────
        [
            'theme' => 'Société', 'weight' => 1, 'axe_ideologique' => 'societal',
            'label' => "L'égalité complète entre les femmes et les hommes dans le monde du travail, notamment en matière de salaires, doit faire l'objet de mesures contraignantes.",
            'explanation' => "Malgré l'interdiction légale des discriminations salariales entre femmes et hommes en Belgique, un écart de rémunération subsiste en moyenne entre les deux, notamment lié à des différences de temps de travail, de secteurs d'emploi ou de progression de carrière. Le débat porte sur le fait d'imposer aux entreprises des obligations plus strictes (transparence des salaires, sanctions) pour réduire cet écart plus rapidement.",
            'positions' => [
                'PS' => [2, "Le PS veut renforcer les obligations de l'employeur en matière de transparence salariale et prévoir des sanctions véritablement dissuasives contre les écarts salariaux entre femmes et hommes.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR ne porte pas de mesures contraignantes spécifiques sur l'égalité salariale dans son programme 2024, privilégiant une approche non coercitive.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, "Écolo défend des mesures contraignantes de transparence salariale et de lutte contre les discriminations à l'embauche liées au genre.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB revendique des mesures contraignantes fortes contre les inégalités salariales entre femmes et hommes, dans le cadre plus large de sa lutte contre les inégalités sociales.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [1, 'DéFI soutient des mesures de lutte contre les discriminations salariales de genre, sans en faire un axe aussi central que la gauche.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [1, "Les Engagés proposent un système de transparence des rémunérations, des contrôles effectifs en entreprise et des sanctions financières adaptées à la taille de l'entreprise pour lutter contre les inégalités salariales.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Société', 'weight' => 1, 'axe_ideologique' => 'societal',
            // Reformulation validée par l'expert politique le 2026-08-09, en
            // remplacement de la formulation initiale ("...sont suffisamment
            // protégés... et ne nécessitent pas de législation supplémentaire"),
            // dont le score et la justification se contredisaient pour
            // PS/Écolo/PTB/DéFI.
            'label' => 'Les droits des personnes LGBTQIA+ doivent encore être renforcés par de nouvelles mesures législatives et politiques.',
            'explanation' => "Depuis les années 2000, la Belgique a adopté plusieurs lois pionnières sur les droits des personnes LGBTQIA+, comme le mariage entre personnes de même sexe en 2003 ou la loi sur le changement de mention du genre à l'état civil. Le débat porte sur le fait de savoir si de nouvelles mesures législatives sont encore nécessaires aujourd'hui, par exemple sur la santé des personnes transgenres ou la lutte contre les discriminations, ou si le cadre légal actuel est suffisant.",
            'positions' => [
                'PS' => [2, "Le PS veut développer un nouveau plan d'action interfédéral contre les discriminations et violences envers les personnes LGBTQIA+, ce qui traduit une volonté de renforcer la législation existante.", 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR est le seul des six partis francophones à s'être positionné contre la suppression du marqueur de genre sur la carte d'identité, une position plus conservatrice qui se rapproche partiellement de l'idée que le cadre actuel est suffisant.", 'Position publique du MR'],
                'ECOLO' => [2, "Écolo veut faire de la Belgique un pays leader de la défense des droits LGBTQIA+ en Europe et dans le monde, avec un programme détaillé sur la santé trans et l'éducation à la diversité, ce qui illustre une volonté forte de renforcement législatif.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, 'Le PTB soutient un renforcement de la lutte contre les discriminations LGBTQIA+ dans le cadre de son combat plus large contre toutes les discriminations sociales.', 'Programme électoral 2024 (PTB)'],
                'DEFI' => [1, "DéFI n'a pas fait des droits LGBTQIA+ un axe central de son programme électoral 2024, se concentrant davantage sur la sécurité dans l'espace public, mais ne s'oppose pas à un renforcement ciblé de la législation existante.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [2, 'Les Engagés veulent faire de la garantie des droits LGBT une priorité et soutiennent un renforcement de la législation, tout en proposant de consulter la société civile par référendum sur des sujets sensibles comme la GPA.', 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Société', 'weight' => 2, 'axe_ideologique' => 'aucun',
            'label' => "La Belgique devrait renforcer significativement son budget consacré à la défense et à l'armée.",
            'explanation' => "Les pays membres de l'OTAN, dont la Belgique, se sont fixé un objectif commun de consacrer 2 % de leur produit intérieur brut (PIB) aux dépenses de défense, un seuil que la Belgique n'atteint pas encore actuellement. Le débat porte sur le fait d'augmenter significativement ce budget militaire, notamment dans le contexte sécuritaire européen actuel, ou de privilégier d'autres priorités budgétaires.",
            'positions' => [
                'PS' => [-1, "Le PS estime qu'il ne faut pas dépenser plus mais investir mieux et ensemble à l'échelle européenne, sans souscrire à l'objectif des 2% du PIB de l'OTAN.", 'Programme électoral 2024 (PS)'],
                'MR' => [2, "Le MR souscrit pleinement à l'objectif des 2% du PIB de l'OTAN pour la défense et veut l'atteindre le plus rapidement possible.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [-1, "Écolo est favorable à une défense européenne mais ne plaide pas pour des dépenses militaires atteignant l'objectif des 2% du PIB de l'OTAN.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [-2, "Le PTB s'oppose frontalement à toute augmentation du budget militaire, y voyant un mauvais choix qui prive les politiques sociales de moyens.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [2, "DéFI souscrit à l'objectif des 2% du PIB de l'OTAN et plaide pour un pilier européen de défense au sein de l'Alliance.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [2, "Les Engagés souscrivent à l'objectif des 2% du PIB de l'OTAN, visant la période 2024-2034, avec au moins 20% du budget consacré aux équipements lourds.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Société', 'weight' => 1, 'axe_ideologique' => 'economique',
            'label' => 'Les dérives des réseaux sociaux et des grandes plateformes numériques doivent être mieux encadrées par la loi.',
            'explanation' => "Les grandes plateformes numériques (réseaux sociaux, moteurs de recherche) collectent d'importantes quantités de données personnelles et diffusent des contenus dont la véracité ou l'impact sur les utilisateurs, notamment les plus jeunes, font l'objet de débats croissants. Le débat porte sur le niveau de régulation légale que ces plateformes devraient respecter, par exemple en matière de protection des données, de désinformation ou de fiscalité.",
            'positions' => [
                'PS' => [2, 'Le PS soutient une taxation renforcée des géants du numérique et une régulation stricte de leurs pratiques fiscales et commerciales au niveau européen.', 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR privilégie une régulation proportionnée qui ne freine pas l'innovation numérique, se montrant plus réservé face à une régulation stricte des plateformes.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [2, 'Écolo soutient une régulation stricte des GAFAM, notamment via la taxation des géants du numérique pour financer la transition écologique et sociale.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB défend une régulation stricte des multinationales du numérique dans le cadre plus large de sa lutte contre l'évasion fiscale des grandes entreprises.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [1, 'DéFI soutient une régulation renforcée des plateformes numériques, sans en faire un axe central de son programme.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [1, 'Les Engagés proposent une taxation des géants du numérique (GAFA) au niveau européen pour financer les transitions, tout en restant modérés sur une régulation stricte plus large.', 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Société', 'weight' => 2, 'axe_ideologique' => 'aucun',
            'label' => "L'État belge devrait inscrire dans la Constitution un principe explicite de neutralité ou de laïcité de l'État.",
            'explanation' => "La Constitution belge garantit la liberté de culte et de conviction, mais elle ne contient pas de principe explicite de laïcité ou de neutralité de l'État, contrairement à certains pays voisins comme la France. Le débat porte sur l'intérêt d'inscrire un tel principe dans la Constitution belge, ce qui pourrait notamment concerner le port de signes convictionnels (religieux ou philosophiques) par les agents de la fonction publique.",
            'positions' => [
                'PS' => [2, "Le PS consacre un chapitre entier de son programme à la laïcité et veut inscrire ce principe dans la Constitution, tout en interdisant les signes convictionnels pour les agents publics exerçant des fonctions d'autorité.", 'Programme électoral 2024 (PS)'],
                'MR' => [1, "Le MR veut inscrire la neutralité de l'État dans la Constitution et interdire les signes convictionnels dans la fonction publique et l'école officielle, sans utiliser le terme de 'laïcité'.", 'Programme électoral 2024 (MR)'],
                'ECOLO' => [-1, "Écolo privilégie une 'neutralité inclusive', posant la liberté de porter des signes convictionnels comme principe de base et l'interdiction comme l'exception, une position nettement moins stricte que celle du PS ou de DéFI.", 'Programme électoral 2024 (Écolo)'],
                'PTB' => [-1, "Le PTB plaide pour une approche inclusive où les communautés religieuses s'organisent librement, sans imposition artificielle par le gouvernement, une ligne éloignée d'une laïcité stricte inscrite dans la Constitution.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [2, "DéFI est le parti qui pousse le plus loin la demande d'inscription de la laïcité politique dans la Constitution, y voyant un rempart contre les intégrismes.", 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [0, "Les Engagés défendent une 'neutralité bienveillante' de l'État plutôt qu'une laïcité stricte inscrite dans la Constitution, autorisant le port de signes convictionnels sauf pour les responsabilités exécutives.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
        [
            'theme' => 'Société', 'weight' => 2, 'axe_ideologique' => 'aucun',
            'label' => 'Le vote devrait être rendu obligatoire pour tous les citoyens belges, y compris pour les élections régionales et européennes.',
            'explanation' => "En Belgique, le vote est obligatoire depuis plus d'un siècle pour les élections fédérales, régionales et européennes, sous peine d'amende en cas d'absence répétée et non justifiée, contrairement à la majorité des pays européens où le vote est facultatif. Le débat porte sur le maintien ou non de cette obligation, certains estimant qu'elle garantit une meilleure représentativité, d'autres qu'elle contredit la liberté individuelle de participer ou non aux élections.",
            'positions' => [
                'PS' => [2, 'Le PS défend fermement le maintien du vote obligatoire à tous les niveaux de pouvoir, y voyant une garantie de représentativité et de cohésion sociale.', 'Programme électoral 2024 (PS)'],
                'MR' => [-1, "Le MR est le parti francophone qui revient le plus régulièrement sur l'idée de supprimer l'obligation de vote, au nom de la liberté individuelle, même s'il a formellement renoncé à cette suppression lors de son congrès de 2016.", 'Congrès du MR (2016)'],
                'ECOLO' => [2, 'Écolo défend non seulement le maintien du vote obligatoire à tous les échelons de pouvoir, mais va plus loin en insistant sur son rôle démocratique essentiel.', 'Programme électoral 2024 (Écolo)'],
                'PTB' => [2, "Le PTB est opposé à la suppression du vote obligatoire, la jugeant contraire à l'égalité de représentation de tous les citoyens.", 'Programme électoral 2024 (PTB)'],
                'DEFI' => [2, 'DéFI se positionne fermement pour le vote obligatoire à tous les échelons, en particulier au niveau local où la confiance citoyenne est la plus forte.', 'Programme électoral 2024 (DéFI)'],
                'ENGAGES' => [2, "Les Engagés affirment être pour le vote obligatoire à tous les échelons, d'autant plus au niveau local, estimant que le degré de confiance y est le plus élevé.", 'Programme électoral 2024 (Les Engagés)'],
            ],
        ],
    ],
];
