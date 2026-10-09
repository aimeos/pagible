<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Str;


/**
 * Counsel theme demo for the fictional Arndt & Keller law firm.
 */
class CounselDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'attorneys' => 'Meet the attorneys of Arndt & Keller in Frankfurt: certified specialists in corporate, employment, real estate, family, inheritance and criminal law.',
        'careers' => 'Careers at Arndt & Keller in Frankfurt: associates, trainee lawyers and legal assistants in a partner-led law firm.',
        'consultation' => 'Book a consultation with Arndt & Keller in Frankfurt. Tell us about your matter and get an appointment within two working days.',
        'corporate-law' => 'Corporate law and M&A in Frankfurt: company formations, shareholder agreements, acquisitions, restructurings and board advice.',
        'criminal-defense' => 'Criminal defense in Frankfurt: police questioning, searches, arrests, white-collar crime and tax offences, with a 24/7 hotline.',
        'dismissal-guide' => 'Received a notice of dismissal in Germany? You have three weeks to file an unfair dismissal claim. What to do now, step by step.',
        'employment-law' => 'Employment law in Frankfurt for employees and employers: dismissals, severance, contracts, works councils and managing directors.',
        'family-law' => 'Family law in Frankfurt: separation, divorce, custody, maintenance and prenuptial agreements, handled discreetly and fairly.',
        'founders-guide' => 'What a shareholder agreement for founders in Germany should cover: vesting, leaver clauses, drag-along, tag-along and deadlock.',
        'imprint' => 'Legal notice of Arndt & Keller Rechtsanwälte, Frankfurt am Main.',
        'inheritance-law' => 'Inheritance law in Frankfurt: wills, inheritance contracts, compulsory portions, disputes among heirs and business succession.',
        'insights' => 'Legal insights from Arndt & Keller in Frankfurt: practical guides on dismissals, shareholder agreements and wills under German law.',
        'litigation' => 'Commercial litigation and arbitration in Frankfurt: contract disputes, liability claims, injunctions and enforcement.',
        'practice-areas' => 'Practice areas of Arndt & Keller in Frankfurt: corporate, employment, real estate, litigation, startups, family, inheritance and criminal law.',
        'privacy' => 'Privacy policy of Arndt & Keller Rechtsanwälte, Frankfurt am Main.',
        'real-estate-law' => 'Real estate law in Frankfurt: property purchases, commercial leases, construction contracts and disputes with landlords and builders.',
        'results' => 'Selected matters of Arndt & Keller in Frankfurt: transactions, court decisions and settlements across our practice areas.',
        'startups-venture' => 'Legal advice for startups in Frankfurt: GmbH formation, financing rounds, term sheets, employee participation and exits.',
        'will-guide' => 'How to make a valid will in Germany: handwritten or notarial will, joint wills for couples and compulsory portions.',
    ];

    /**
     * Curated Unsplash photos used by the law firm demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'columns' => ['photo-1483600516620-7254872369ae', 'Neoclassical columns', 'Low-angle view up between white fluted stone columns to a coffered ceiling'],
        'consultation' => ['photo-1758518730327-98070967caab', 'Client consultation', 'Attorney in a dark suit showing a document to a client across the desk'],
        'courthouse' => ['photo-1603644448048-28a7e5122f0a', 'Palace of Justice', 'Monumental neoclassical columns and ornate stonework inside a court building'],
        'elderly-couple' => ['photo-1625690987114-86f5af994b49', 'Planning ahead', 'Grey-haired elderly couple walking arm in arm down a tree-lined road'],
        'family' => ['photo-1542037104857-ffbb0b9155fb', 'Family', 'Parents and three young children walking hand in hand through a green meadow'],
        'father' => ['photo-1609220136736-443140cffec6', 'Father with children', 'Smiling father with glasses carrying two young children outdoors'],
        'files' => ['photo-1583521214690-73421a1829a9', 'Case files', 'Tall stacks of paper documents and file folders in an office'],
        'gavel' => ['photo-1589391886645-d51941baf7fb', 'Gavel', 'Wooden judge gavel and sound block on a white marble surface'],
        'gavel-plain' => ['photo-1593115057322-e94b77572f20', 'Criminal defense', 'Wooden judge gavel resting on a plain light surface'],
        'handshake' => ['photo-1521791136064-7986c2920216', 'Agreement', 'Close-up of two businesspeople shaking hands in an office'],
        'justice' => ['photo-1589578527966-fdac0f44566c', 'Lady Justice', 'Bronze statue of Lady Justice holding scales and a sword'],
        'keys' => ['photo-1560518883-ce09059eeffa', 'Real estate', 'Small model house and a set of keys on a wooden table'],
        'library' => ['photo-1531429745839-827a6a45e040', 'Law library', 'Grand library reading room with wood-panelled bookshelves and long study desks'],
        'meeting' => ['photo-1758518731462-d091b0b4ed0d', 'Team meeting', 'Three colleagues in business attire discussing documents around a table'],
        'office' => ['photo-1774186184471-32c1339d2d8c', 'Our office', 'Modern high-floor office with wooden desks and floor-to-ceiling windows overlooking the city'],
        'office-staff' => ['photo-1560264280-88b68371db39', 'Employment', 'Employees working at desks in a large bright open-plan office'],
        'pen' => ['photo-1455390582262-044cdead277a', 'Fountain pen', 'Close-up of a fountain pen nib writing in ink on lined paper'],
        'portrait-arndt' => ['photo-1714974528904-8dd7595ef1a7', 'Dr. Katharina Arndt', 'Attorney with blonde hair and dark-framed glasses in a white blouse in a bright office'],
        'portrait-brenner' => ['photo-1718209881007-c0ecdfc00f9d', 'Leon Brenner', 'Dark-haired attorney in a dark suit standing with his arms crossed between stone columns'],
        'portrait-dubois' => ['photo-1733348137479-2e726d326d9b', 'Claire Dubois', 'Smiling attorney with blonde hair in a black blazer standing with her arms crossed'],
        'portrait-keller' => ['photo-1790596685394-4a6c0ca886f2', 'Markus Keller', 'Senior attorney with grey hair and glasses in a dark jacket in an office'],
        'portrait-lindner' => ['photo-1685760259914-ee8d2c92d2e0', 'Sophie Lindner', 'Smiling attorney with auburn hair and a navy blazer standing outdoors in the city'],
        'portrait-okafor' => ['photo-1573496527892-904f897eb744', 'Amara Okafor', 'Smiling attorney with curly hair and glasses wearing a pale pink blazer'],
        'portrait-schreiber' => ['photo-1648474484044-bb82df2f5a1f', 'Daniel Schreiber', 'Young attorney in a navy suit, white shirt and striped tie'],
        'portrait-weber' => ['photo-1560250097-0b93528c311a', 'Dr. Jonas Weber', 'Attorney in his forties with dark-rimmed glasses and a dark blazer in a bright office'],
        'signing' => ['photo-1450101499163-c8848c66ca85', 'Signing a contract', 'Close-up of a hand signing a document with a pen'],
        'skyline' => ['photo-1626447637943-4c9d412fa8cf', 'Frankfurt skyline', 'Frankfurt banking district skyline at dusk above the old town'],
        'startup' => ['photo-1522071820081-009f0129c71c', 'Startup team', 'Young team working together on laptops around a wooden table'],
        'towers' => ['photo-1486406146926-c627a92ad1ab', 'Office towers', 'Low-angle view of dark glass skyscrapers rising into a cloudy sky'],
    ];

    private string $element;
    private string $insightsId;
    /** @var array<string, string> Icon file IDs keyed by name */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the attorneys page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAttorneys( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Attorneys',
            'title' => 'Our Attorneys | Arndt & Keller Frankfurt',
            'path' => 'attorneys',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'You work with a partner, not a team of strangers',
                'subtitle' => 'Our attorneys',
                'text' => 'Eight attorneys, five of them certified specialists. The partner who takes on your matter stays your contact until it is resolved.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                ],
                'background' => ['id' => $this->img( 'library' ), 'type' => 'file'],
            ]],
            $this->team( 'Partners', ['arndt', 'keller', 'weber', 'dubois'] ),
            $this->team( 'Counsel and associates', ['okafor', 'lindner', 'schreiber', 'brenner'] ),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Since 1998 in Frankfurt\n\nDr. Katharina Arndt and Markus Keller founded the firm in 1998 in two rooms on Bockenheimer Landstraße. Today, eight attorneys and twelve staff advise companies, founders and private clients from the same address, now on the 14th floor.\n\nWe have stayed deliberately small. Every matter is led by a partner, every client gets a direct phone number, and we decline cases rather than delegate them to people you have never met.",
            ]],
            $this->recognition(),
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Join Arndt & Keller',
                'text' => 'We are looking for associates and trainee lawyers who want responsibility from the first day.',
                'buttons' => [
                    ['label' => 'Careers', 'url' => '/careers'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the careers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCareers( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Careers',
            'title' => 'Careers for Lawyers in Frankfurt | Arndt & Keller',
            'path' => 'careers',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Real cases from your first week',
                'subtitle' => 'Careers',
                'text' => 'At Arndt & Keller, you work directly with a partner, meet clients from the start and appear in court in your first year.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:careers@arndt-keller.example'],
                ],
                'background' => ['id' => $this->img( 'office' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'meeting' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you can expect\n\n- A salary at the level of large commercial firms, without billable hour targets\n- Two days a week from home, if your cases allow it\n- Funding for your specialist lawyer course (Fachanwaltslehrgang)\n- A mentor among the partners and a yearly development talk\n- An office with a view over Frankfurt and a team lunch every Friday",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Associate, employment law (m/f/d)', 'text' => 'Two state exams, ideally first experience in employment law. Full or part time.'],
                    ['title' => 'Trainee lawyer (Referendar, m/f/d)', 'text' => 'Station or elective station in corporate, employment or litigation. Paid in addition to the state allowance.'],
                    ['title' => 'Legal assistant (m/f/d)', 'text' => 'Rechtsanwaltsfachangestellte with experience in deadlines, court filings and the beA mailbox.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Interested?',
                'text' => 'Send us your CV and certificates. A cover letter is optional, and we reply within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:careers@arndt-keller.example'],
                    ['label' => 'Meet our attorneys', 'url' => '/attorneys'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the consultation page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addConsultation( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Consultation',
            'title' => 'Book a Consultation | Arndt & Keller Frankfurt',
            'path' => 'consultation',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => 'consultation', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Book a consultation',
                'description' => 'Tell us briefly what your matter is about. An attorney checks for conflicts of interest and contacts you within two working days. Please don\'t send confidential documents yet. If you have been arrested or your premises are being searched, call our hotline **+49 69 5550 4199** instead, day and night.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Practice area', 'required' => true, 'input' => 'select', 'options' => "Corporate and M&A\nEmployment law\nReal estate law\nCommercial litigation\nStartups and venture capital\nFamily law\nInheritance law\nCriminal defense\nNot sure"],
                    ['field' => 'I am', 'required' => true, 'input' => 'select', 'options' => "A private client\nA company\nA founder"],
                    ['field' => 'Other parties involved', 'required' => false, 'input' => 'text'],
                    ['field' => 'Deadline', 'required' => false, 'input' => 'text'],
                    ['field' => 'Your matter in brief', 'required' => true, 'input' => 'textarea'],
                ],
            ]],
            $this->fees(),
            $this->map( 'Our office' ),
        ], $home );

        return $this;
    }


    /**
     * Creates the imprint page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addImprint( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Imprint',
            'title' => 'Imprint | Arndt & Keller',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Arndt & Keller Rechtsanwälte PartG mbB**\nBockenheimer Landstraße 51\n60325 Frankfurt am Main\nGermany\n\nTelephone: +49 69 5550 4100\nEmail: office@arndt-keller.example\n\nPartnership register: Frankfurt am Main Local Court, PR 0000\nPartners: Dr. Katharina Arndt, Markus Keller, Dr. Jonas Weber, Claire Dubois\nVAT ID: DE000000000\n\n## Professional information\n\nAll attorneys hold the professional title Rechtsanwältin/Rechtsanwalt, awarded in the Federal Republic of Germany.\n\nCompetent chamber: Rechtsanwaltskammer Frankfurt am Main\n\nProfessional regulations: Federal Lawyers' Act (BRAO), Rules of Professional Practice (BORA), Specialist Lawyers' Regulations (FAO), Lawyers' Remuneration Act (RVG), Code of Conduct for European Lawyers (CCBE). The texts are available at www.brak.de.\n\nProfessional liability insurance: Example Insurance AG, Example Street 1, 80331 Munich, Germany. Coverage: Germany and the European Union.\n\n## Dispute resolution\n\nFor disputes about fees, you can contact the Rechtsanwaltskammer Frankfurt am Main or the arbitration board of the legal profession (Schlichtungsstelle der Rechtsanwaltschaft), Neue Grünstraße 17, 10179 Berlin. We are willing to take part in dispute resolution proceedings before the arbitration board.\n\nThis is a demo website for the Counsel theme. Arndt & Keller is a fictional law firm, the people shown are not real attorneys and nothing on this website is legal advice.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the insights page and its articles below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addInsights( Page $home ) : static
    {
        $insights = $this->insights( $home );

        $this->insight( $insights, [
            'name' => 'Dismissed? Three weeks to act',
            'title' => 'Notice of Dismissal in Germany: What to Do Within Three Weeks',
            'path' => 'dismissal-guide',
        ], 'Received a notice of dismissal? The clock is running',
            "If you want to challenge a dismissal in Germany, you must file an unfair dismissal claim with the labour court within three weeks of receiving the written notice. If you miss the deadline, the dismissal is treated as valid, however weak the employer's reasons were.\n\nThe claim is also your strongest negotiating position. Most cases end with a settlement in the first hearing, often with a severance payment that employers would never have offered otherwise.",
            'office-staff',
            [
                ['title' => '3 weeks', 'text' => 'To file a claim after receiving the notice'],
                ['title' => '0.5', 'text' => 'Monthly salaries per year of service, a common severance rule of thumb'],
                ['title' => '6 weeks', 'text' => 'Until the first hearing at the Frankfurt labour court, on average'],
            ],
            [
                ['label' => 'Day 1', 'title' => 'Note the date', 'text' => 'Write down when and how you received the notice. The deadline starts then.'],
                ['label' => 'Days 1–3', 'title' => 'Register as job seeker', 'text' => 'Register with the employment agency within three days to protect your benefits.'],
                ['label' => 'Week 1', 'title' => 'Get advice', 'text' => 'Bring the notice, your contract and your last pay slips to the consultation.'],
                ['label' => 'Week 3', 'title' => 'File the claim', 'text' => 'We file the claim and start negotiating with your employer.'],
            ],
            [
                ['title' => 'Should I sign a termination agreement instead?', 'text' => 'Not before you have had it checked. A termination agreement can lead to a twelve-week block on unemployment benefits and usually waives your right to sue.'],
                ['title' => 'Who pays the lawyer in a labour court case?', 'text' => 'In the first instance, each side pays its own costs, whatever the outcome. Legal expenses insurance usually covers employment matters.'],
                ['title' => 'Does the Protection Against Dismissal Act apply to me?', 'text' => 'It applies if you have worked for the company for more than six months and it regularly employs more than ten people.'],
            ],
        );

        $this->insight( $insights, [
            'name' => 'The founders\' agreement',
            'title' => 'Shareholder Agreements for Founders: What to Agree Before You Raise',
            'path' => 'founders-guide',
        ], 'Agree on the hard questions while you still agree',
            "The articles of association of a German GmbH cover the basics. What happens when a co-founder leaves, how shares vest and who decides in a deadlock belongs in a shareholder agreement, and investors will ask for it in the first financing round.\n\nIt is far easier to settle these questions at the kitchen table than after the first argument. A clear agreement also makes your company more attractive to investors, because it shows that the founding team has thought ahead.",
            'startup',
            [
                ['title' => '4 years', 'text' => 'The usual vesting period for founder shares'],
                ['title' => '1 year', 'text' => 'Cliff before the first shares vest'],
                ['title' => '1', 'text' => 'Notary appointment if the agreement contains share transfer obligations'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Roles and time', 'text' => 'Who does what, full time or part time, and what if that changes.'],
                ['label' => 'Step 2', 'title' => 'Vesting and leavers', 'text' => 'How shares vest and at what price good and bad leavers must sell them.'],
                ['label' => 'Step 3', 'title' => 'Decisions', 'text' => 'Which decisions need unanimity and how a deadlock is resolved.'],
                ['label' => 'Step 4', 'title' => 'Exit', 'text' => 'Drag-along and tag-along rights for a later sale of the company.'],
            ],
            [
                ['title' => 'Does a shareholder agreement have to be notarised?', 'text' => 'If it obliges shareholders to transfer GmbH shares, for example in leaver clauses, it must be notarised to be valid. Purely contractual rules can be signed privately.'],
                ['title' => 'What does a shareholder agreement cost?', 'text' => 'For a typical founding team, we agree a fixed fee in advance. It depends on the number of founders and whether investors are already involved.'],
                ['title' => 'Can we change it later?', 'text' => 'Yes, with the consent of all parties. A financing round is usually the moment when it is updated anyway.'],
            ],
        );

        $this->insight( $insights, [
            'name' => 'A valid will in Germany',
            'title' => 'How to Make a Valid Will in Germany',
            'path' => 'will-guide',
        ], 'Your will, in your own words, and valid',
            "Without a will, German law decides who inherits, and the result often surprises families. A spouse may have to share the family home with the children, and unmarried partners inherit nothing at all.\n\nA will can be handwritten or made before a notary. Both are valid, but small formal mistakes can make a handwritten will void, and unclear wording is the most common cause of disputes among heirs.",
            'elderly-couple',
            [
                ['title' => '100%', 'text' => 'Of a handwritten will must be written and signed by hand'],
                ['title' => '50%', 'text' => 'Of the statutory share is the compulsory portion of children and spouses'],
                ['title' => '1×', 'text' => 'Registration in the central register of wills is enough'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Take stock', 'text' => 'List your assets, debts, real estate and company shares.'],
                ['label' => 'Step 2', 'title' => 'Decide', 'text' => 'Who should inherit, who gets specific items and who should be executor.'],
                ['label' => 'Step 3', 'title' => 'Write', 'text' => 'By hand, with date, place and signature, or before a notary.'],
                ['label' => 'Step 4', 'title' => 'Deposit', 'text' => 'Deposit it with the local court so that it will be found.'],
            ],
            [
                ['title' => 'Can a couple write one will together?', 'text' => 'Married couples and registered partners can make a joint will, often the so-called Berlin will. It can bind the surviving partner, so the wording needs care.'],
                ['title' => 'Can I disinherit my children?', 'text' => 'You can, but they keep a claim to the compulsory portion, which is half of their statutory share, paid in money.'],
                ['title' => 'Is a will typed on a computer valid?', 'text' => 'No. A private will must be written entirely by hand and signed. A typed and signed document is void.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the practice areas page and the practice area pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPractice( Page $home ) : static
    {
        $practice = $this->page( [
            'lang' => 'en',
            'name' => 'Practice areas',
            'title' => 'Practice Areas | Arndt & Keller Law Firm Frankfurt',
            'path' => 'practice-areas',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Clear advice for decisions that matter',
                'subtitle' => 'Practice areas',
                'text' => 'For companies, founders and private clients: eight practice areas, each led by a partner or certified specialist who knows the Frankfurt courts.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                ],
                'background' => ['id' => $this->img( 'columns' ), 'type' => 'file'],
            ]],
            $this->practices(),
            $this->fees(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you take on cases outside Frankfurt?', 'text' => 'Yes. We advise clients throughout Germany and appear before all German courts. For matters abroad, we work with partner firms in the major European cities.'],
                    ['title' => 'Do you accept legal expenses insurance?', 'text' => 'Yes. We request cover from your insurer for you before we start, so you know which costs are covered.'],
                    ['title' => 'Can I get legal aid?', 'text' => 'If your income is low, the court can grant legal aid for court proceedings, and the local court can issue a counselling voucher for advice. We help you with the application.'],
                    ['title' => 'Do you advise in English?', 'text' => 'Yes. All our attorneys advise in English, several also in French, Polish, Turkish or Igbo.'],
                ],
            ]],
        ], $home );

        foreach( $this->areas() as $area ) {
            $this->area( $practice, ...$area );
        }

        return $this;
    }


    /**
     * Creates the privacy policy page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrivacy( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Privacy',
            'title' => 'Privacy Policy | Arndt & Keller',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nArndt & Keller Rechtsanwälte PartG mbB, Bockenheimer Landstraße 51, 60325 Frankfurt am Main, privacy@arndt-keller.example.\n\n## Consultation requests\n\nWhen you send the consultation form, we use your details to check for conflicts of interest and to contact you (Art. 6 (1) (b) GDPR). Requests that don't lead to a mandate are deleted after six months.\n\n## Client files\n\nAs attorneys, we are bound by professional secrecy. We keep client files for six years after the end of the mandate, as required by the Federal Lawyers' Act, and longer where tax law requires it. Files are stored on servers in Germany and are only accessible to the attorneys and staff working on your matter.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Hessian Commissioner for Data Protection and Freedom of Information.\n\nThis is a demo website for the Counsel theme. Arndt & Keller is a fictional law firm.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the case results page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addResults( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Results',
            'title' => 'Selected Matters and Results | Arndt & Keller Frankfurt',
            'path' => 'results',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Results speak more clearly than promises',
                'subtitle' => 'Selected matters',
                'text' => 'A selection of transactions, judgments and settlements from recent years, published with the consent of our clients and without their names.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                ],
                'background' => ['id' => $this->img( 'courthouse' ), 'type' => 'file'],
            ]],
            $this->figures(),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Corporate and commercial',
                'columns' => '3',
                'cards' => [
                    ['title' => '€140 million', 'text' => 'Sale of a family-owned logistics group to a strategic buyer, including the reinvestment of the founding family.', 'url' => '/corporate-law'],
                    ['title' => '€4.8 million', 'text' => 'Damages awarded to a mechanical engineering company against a supplier for defective components, upheld on appeal.', 'url' => '/litigation'],
                    ['title' => '€12 million', 'text' => 'Series A financing of a Frankfurt fintech startup, from term sheet to closing in seven weeks.', 'url' => '/startups-venture'],
                    ['title' => '22,000 m²', 'text' => 'Long-term lease of an office building in the banking district for an international bank.', 'url' => '/real-estate-law'],
                    ['title' => 'Injunction in 48 hours', 'text' => 'Interim injunction against a former managing director who was soliciting customers.', 'url' => '/litigation'],
                    ['title' => '9 days', 'text' => 'Formation of a GmbH with three founders, shareholder agreement and VSOP included.', 'url' => '/startups-venture'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Private clients',
                'columns' => '3',
                'cards' => [
                    ['title' => '€185,000', 'text' => 'Severance for a sales director after an unfair dismissal claim, agreed in the first hearing.', 'url' => '/employment-law'],
                    ['title' => 'Proceedings dropped', 'text' => 'Tax evasion investigation against an entrepreneur discontinued before charges were brought.', 'url' => '/criminal-defense'],
                    ['title' => '€640,000', 'text' => 'Compulsory portion enforced for two siblings who had been disinherited by their father.', 'url' => '/inheritance-law'],
                    ['title' => 'Joint custody', 'text' => 'Shared parenting arrangement agreed out of court for a family moving between Frankfurt and Lyon.', 'url' => '/family-law'],
                    ['title' => 'Reinstated', 'text' => 'Works council member reinstated after the labour court declared the dismissal void.', 'url' => '/employment-law'],
                    ['title' => '€96,000', 'text' => 'Purchase price reduction for hidden water damage in a renovated period apartment.', 'url' => '/real-estate-law'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => '*Every case is different. Prior results don\'t guarantee a similar outcome in your matter.*',
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'How strong is your case?',
                'text' => 'In a first consultation, we assess your chances, the risks and the costs honestly, also when the answer is not what you hoped to hear.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'Call +49 69 5550 4100', 'url' => 'tel:+496955504100'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates a practice area page below the practice areas page.
     *
     * @param Page $parent Practice areas page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Hero headline
     * @param string $hero PHOTOS key of the hero image
     * @param string $image PHOTOS key of the text image
     * @param string $text Practice area description
     * @param array<int, array<string, string>> $items Typical matters
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function area( Page $parent, array $data, string $title, string $hero, string $image,
        string $text, array $items, array $questions ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => $title,
                'subtitle' => $data['name'],
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'All practice areas', 'url' => '/practice-areas'],
                ],
                'background' => ['id' => $this->img( $hero ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( $image ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => $text,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'How we help',
                'cards' => $items,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from our clients',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let\'s discuss your matter',
                'text' => 'Book a consultation online or call us. You get an appointment within two working days, urgent matters on the same day.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'Call +49 69 5550 4100', 'url' => 'tel:+496955504100'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the arguments for the practice area pages.
     *
     * @return array<int, array<int, mixed>> Page data, headline, hero and image keys, text, typical matters and questions
     */
    protected function areas() : array
    {
        return [
            [['name' => 'Corporate and M&A', 'title' => 'Corporate Law and M&A in Frankfurt | Arndt & Keller', 'path' => 'corporate-law'],
                'Transactions that hold up years later', 'towers', 'handshake',
                "## From formation to exit\n\nWe advise owner-managed companies, family businesses and investors on every step in the life of a company: formations and restructurings, shareholder agreements, board matters, acquisitions and sales.\n\nIn transactions, we lead the due diligence, negotiate the purchase agreement and coordinate tax advisers, notaries and banks, so that you have one contact who keeps the whole deal together.\n\nThe practice is led by [Dr. Katharina Arndt](/attorneys), who has advised on more than 120 transactions. Read our guide on [shareholder agreements for founders](/founders-guide).",
                [
                    ['title' => 'Mergers and acquisitions', 'text' => 'Purchases and sales of companies and shareholdings, from letter of intent to closing.'],
                    ['title' => 'Shareholder disputes', 'text' => 'Deadlocks, exclusions and exits, negotiated or before the courts.'],
                    ['title' => 'Succession', 'text' => 'Handing over a family business to the next generation or a buyer.'],
                ],
                [
                    ['title' => 'When should we involve a lawyer in a sale?', 'text' => 'Before you sign a letter of intent. Key points such as exclusivity, price mechanisms and confidentiality are often set at this stage.'],
                    ['title' => 'How are transactions billed?', 'text' => 'Usually on an hourly basis with a cap, or with a fixed fee for clearly defined phases such as the due diligence.'],
                ]],
            [['name' => 'Employment law', 'title' => 'Employment Lawyer in Frankfurt | Arndt & Keller', 'path' => 'employment-law'],
                'Your rights at work, firmly represented', 'office-staff', 'consultation',
                "## For employees and employers\n\nWe represent employees, executives and managing directors in dismissals, severance negotiations and disputes about bonuses and non-compete clauses. For employers, we draft contracts, negotiate with works councils and support restructurings.\n\nIf you have received a notice of dismissal, time matters: an unfair dismissal claim must be filed within three weeks. Call us, and we will see you within two working days.\n\nThe practice is led by [Dr. Jonas Weber](/attorneys), certified specialist in employment law. Read [what to do after a dismissal](/dismissal-guide).",
                [
                    ['title' => 'Dismissal and severance', 'text' => 'Unfair dismissal claims and severance negotiations, in and out of court.'],
                    ['title' => 'Executives and directors', 'text' => 'Service agreements, dismissals and liability of managing directors.'],
                    ['title' => 'Works councils', 'text' => 'Company agreements, restructurings and conciliation committees.'],
                ],
                [
                    ['title' => 'Am I entitled to severance?', 'text' => 'Not automatically. In practice, most dismissal cases end with a settlement that includes severance, especially when the dismissal is open to challenge.'],
                    ['title' => 'Does my legal expenses insurance cover this?', 'text' => 'Most policies with employment cover pay for dismissal cases. We request the cover for you.'],
                ]],
            [['name' => 'Real estate law', 'title' => 'Real Estate Lawyer in Frankfurt | Arndt & Keller', 'path' => 'real-estate-law'],
                'Solid ground for every property deal', 'skyline', 'keys',
                "## Buy, let, build\n\nWe advise buyers, sellers and investors on residential and commercial property, draft and negotiate commercial leases and support construction projects from the contract with the builder to the acceptance of the work.\n\nWhen things go wrong, we enforce warranty claims for defects, defend against unjustified claims and resolve disputes with landlords, tenants and owners' associations.\n\nThe practice is led by [Claire Dubois](/attorneys), certified specialist in tenancy and condominium law.",
                [
                    ['title' => 'Purchase and sale', 'text' => 'Review of notarial contracts, due diligence and financing conditions.'],
                    ['title' => 'Commercial leases', 'text' => 'Office, retail and logistics leases for landlords and tenants.'],
                    ['title' => 'Construction', 'text' => 'Construction contracts, defects, delays and final invoices.'],
                ],
                [
                    ['title' => 'Do I need a lawyer if a notary is involved?', 'text' => 'The notary must be neutral and doesn\'t represent your interests. We check the draft contract on your behalf before the notary appointment.'],
                    ['title' => 'How long do I have to claim for defects?', 'text' => 'For buildings, warranty claims generally become time-barred five years after acceptance or handover, unless the seller fraudulently concealed a defect.'],
                ]],
            [['name' => 'Commercial litigation', 'title' => 'Commercial Litigation in Frankfurt | Arndt & Keller', 'path' => 'litigation'],
                'Prepared for court, open to settlement', 'courthouse', 'gavel',
                "## Disputes resolved with strategy\n\nWe represent companies before the civil courts, the commercial chambers of the Frankfurt Regional Court and in arbitration. Our cases range from contract and liability disputes to interim injunctions, which we often obtain within 48 hours.\n\nEvery case starts with an honest assessment of the chances, risks and costs. Where a settlement serves you better than a judgment, we negotiate it, from a position that is ready for court.\n\nThe practice is led by [Markus Keller](/attorneys), who has conducted more than 600 court cases.",
                [
                    ['title' => 'Contract disputes', 'text' => 'Payment, delivery and warranty claims between companies.'],
                    ['title' => 'Interim injunctions', 'text' => 'Fast court orders against competitors or former partners.'],
                    ['title' => 'Arbitration', 'text' => 'Proceedings under DIS and ICC rules, in German and English.'],
                ],
                [
                    ['title' => 'How long does a civil case take?', 'text' => 'In the first instance, usually nine to fifteen months. Interim injunctions take days, arbitration often less time than two court instances.'],
                    ['title' => 'Who pays the costs?', 'text' => 'In German civil proceedings, the losing party pays the court fees and the statutory fees of both lawyers. We explain the cost risk before you decide.'],
                ]],
            [['name' => 'Startups and venture', 'title' => 'Startup Lawyer in Frankfurt | Arndt & Keller', 'path' => 'startups-venture'],
                'Built to scale from day one', 'startup', 'pen',
                "## Legal advice at startup speed\n\nWe set up your GmbH, draft the shareholder agreement and the employee participation programme and support you through financing rounds, from the term sheet to closing.\n\nFor founders, we work with fixed fees for the standard steps, so you know your costs in advance. For investors, we review target companies and negotiate investment agreements.\n\nThe practice is led by [Amara Okafor](/attorneys), who worked in a Berlin venture capital fund before joining us. Read our guide on [shareholder agreements for founders](/founders-guide).",
                [
                    ['title' => 'Formation', 'text' => 'GmbH or UG, articles of association and shareholder agreement.'],
                    ['title' => 'Financing rounds', 'text' => 'Term sheets, investment agreements and convertible loans.'],
                    ['title' => 'Employee participation', 'text' => 'Virtual stock option plans that your team understands.'],
                ],
                [
                    ['title' => 'GmbH or UG?', 'text' => 'A UG can be founded with just one euro of share capital, but many investors and business partners prefer a GmbH with its €25,000 share capital.'],
                    ['title' => 'What does a formation cost?', 'text' => 'Our founder package starts at a fixed fee of €1,900 plus VAT. Notary and register fees are added.'],
                ]],
            [['name' => 'Family law', 'title' => 'Family Lawyer in Frankfurt | Arndt & Keller', 'path' => 'family-law'],
                'Fair solutions for difficult times', 'family', 'father',
                "## Separation, divorce and children\n\nA separation is one of the most stressful events in life. We help you find fair solutions for maintenance, the family home, the division of assets and, above all, the children.\n\nWhere possible, we reach agreements out of court and work with mediators. Where necessary, we represent you firmly before the family court. We also draft prenuptial and partnership agreements, so that both sides know where they stand.\n\nThe practice is led by [Sophie Lindner](/attorneys), certified specialist in family law.",
                [
                    ['title' => 'Divorce', 'text' => 'Uncontested and contested divorces, including the pension adjustment.'],
                    ['title' => 'Children', 'text' => 'Custody, contact arrangements and child maintenance.'],
                    ['title' => 'Agreements', 'text' => 'Prenuptial, separation and divorce settlement agreements.'],
                ],
                [
                    ['title' => 'How long do we have to be separated before a divorce?', 'text' => 'Usually one year. Spouses can live separately in the same home during that year, if they share neither bed nor household.'],
                    ['title' => 'Do we both need a lawyer?', 'text' => 'Only the spouse who files for divorce must be represented. If you agree on everything, one lawyer can be enough, but they can only represent one of you.'],
                ]],
            [['name' => 'Inheritance law', 'title' => 'Inheritance Lawyer in Frankfurt | Arndt & Keller', 'path' => 'inheritance-law'],
                'Your legacy, arranged the way you want', 'elderly-couple', 'signing',
                "## Plan, settle, resolve\n\nWe help you arrange your estate with a will or inheritance contract that reflects your wishes and keeps the peace in your family, including the succession of a business.\n\nAfter a death, we support heirs in accepting or disclaiming the inheritance, settling the estate and claiming the compulsory portion. When heirs disagree, we negotiate, mediate or go to court.\n\nThe practice is led by [Daniel Schreiber](/attorneys), certified specialist in inheritance law. Read [how to make a valid will in Germany](/will-guide).",
                [
                    ['title' => 'Wills and contracts', 'text' => 'Wills, joint wills and inheritance contracts drafted with care.'],
                    ['title' => 'Compulsory portion', 'text' => 'Claims enforced or defended for disinherited relatives.'],
                    ['title' => 'Disputes among heirs', 'text' => 'Dividing the estate fairly, out of court where possible.'],
                ],
                [
                    ['title' => 'How long can I disclaim an inheritance?', 'text' => 'Six weeks after you learn of the inheritance and the reason for it, or six months if the deceased lived abroad.'],
                    ['title' => 'Can a will be challenged?', 'text' => 'Yes, for example if the testator lacked capacity, made a mistake or was threatened. The deadline is one year after learning of the reason.'],
                ]],
            [['name' => 'Criminal defense', 'title' => 'Criminal Defense Lawyer in Frankfurt | Arndt & Keller', 'path' => 'criminal-defense'],
                'Say nothing. Call us', 'justice', 'files',
                "## Defense from the first minute\n\nWhether you have been summoned by the police, your home or office is being searched or you have been arrested: you have the right to remain silent and to speak to a lawyer first. Use both.\n\nOur hotline **+49 69 5550 4199** reaches a defense lawyer day and night. We request access to the files, speak with the public prosecutor and often achieve that proceedings are dropped before charges are brought.\n\nThe practice is led by [Leon Brenner](/attorneys), certified specialist in criminal law, with a focus on white-collar and tax offences.",
                [
                    ['title' => 'Investigations', 'text' => 'Summons, searches and questioning, from the first contact with the police.'],
                    ['title' => 'White-collar crime', 'text' => 'Fraud, breach of trust, corruption and insolvency offences.'],
                    ['title' => 'Tax offences', 'text' => 'Tax evasion, voluntary disclosures and tax investigations.'],
                ],
                [
                    ['title' => 'Do I have to attend a police summons?', 'text' => 'As a suspect, no. You don\'t have to make a statement to the police. Contact us first, and we request access to the files.'],
                    ['title' => 'What should I do during a search?', 'text' => 'Stay calm, don\'t resist, ask for the search warrant and make no statement. Call our hotline right away.'],
                ]],
        ];
    }


    /**
     * Creates the shared Arndt & Keller footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Arndt & Keller footer', ['columns' => '4', 'cards' => [
            ['title' => 'Office hours', 'text' => "- Mon–Thu: 08:30–18:30\n- Fri: 08:30–16:00\n- Hotline 24/7: [+49 69 5550 4199](tel:+496955504199)"],
            ['title' => 'Practice areas', 'text' => "- [Corporate and M&A](/corporate-law)\n- [Employment law](/employment-law)\n- [Real estate law](/real-estate-law)\n- [Commercial litigation](/litigation)\n- [Family law](/family-law)\n- [Criminal defense](/criminal-defense)"],
            ['title' => 'The firm', 'text' => "- [Our attorneys](/attorneys)\n- [Selected matters](/results)\n- [Insights](/insights)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Bockenheimer Landstraße 51\n60325 Frankfurt am Main\n\n+49 69 5550 4100\n[Book a consultation](/consultation)"],
        ]] );
    }


    /**
     * Returns the fees element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function fees() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Transparent fees',
            'text' => 'You know the costs before we start. Court cases are billed according to the Lawyers\' Remuneration Act (RVG) unless we agree otherwise. All prices plus VAT.',
            'items' => [
                [
                    'name' => 'Initial consultation',
                    'prices' => [['id' => 'first', 'amount' => 190, 'label' => '€190']],
                    'text' => 'For private clients, up to 60 minutes.',
                    'features' => "- Assessment of your case\n- Chances, risks and costs\n- Clear recommendation for the next steps\n- Credited if you instruct us",
                    'url' => '/consultation',
                    'button' => 'Book a consultation',
                ],
                [
                    'name' => 'Ongoing advice',
                    'prices' => [['id' => 'hourly', 'amount' => 290, 'label' => 'from €290 / hour']],
                    'text' => 'For companies and complex matters.',
                    'features' => "- Partner-led, billed in 6-minute units\n- Monthly itemised invoices\n- Cap agreed in advance on request\n- Direct line to your attorney",
                    'url' => '/practice-areas',
                    'button' => 'Practice areas',
                    'highlight' => true,
                    'badge' => 'Most chosen',
                ],
                [
                    'name' => 'Fixed fee',
                    'prices' => [['id' => 'fixed', 'amount' => 1900, 'label' => 'from €1,900']],
                    'text' => 'For clearly defined tasks.',
                    'features' => "- GmbH formation for founders\n- Wills and inheritance contracts\n- Employment contracts and policies\n- Review of purchase contracts",
                    'url' => '/startups-venture',
                    'button' => 'Startups and venture',
                ],
            ],
        ]];
    }


    /**
     * Returns the firm figures element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function figures() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'layout' => 'figures',
            'cards' => [
                ['title' => '1998', 'text' => 'Founded in Frankfurt am Main'],
                ['title' => '3,200+', 'text' => 'Matters concluded for our clients'],
                ['title' => '€1.4bn', 'text' => 'Transaction volume advised on'],
                ['title' => '5', 'text' => 'Certified specialist lawyers'],
            ],
        ]];
    }


    /**
     * Returns the ID of the primary firm image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'skyline' );
    }


    /**
     * Returns the Arndt & Keller home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Arndt & Keller'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'counsel::firm' => [
                'type' => 'counsel::firm',
                'files' => [],
                'data' => [
                    'name' => 'Arndt & Keller Rechtsanwälte',
                    'business-type' => 'LegalService',
                    'street-address' => 'Bockenheimer Landstraße 51',
                    'postal-code' => '60325',
                    'locality' => 'Frankfurt am Main',
                    'country' => 'DE',
                    'telephone' => '+49 69 5550 4100',
                    'email' => 'office@arndt-keller.example',
                    'hotline' => '+49 69 5550 4199',
                    'consultation' => '/consultation',
                    'languages' => 'German, English, French, Polish, Turkish',
                    'practice-areas' => 'Corporate law, M&A, Employment law, Real estate law, Commercial litigation, Startups and venture capital, Family law, Inheritance law, Criminal defense',
                    'price-range' => '€€€',
                    'call-button' => true,
                    'disclaimer' => 'The information on this website is general information, not legal advice. Contacting us doesn\'t create a client relationship, so please don\'t send confidential information until we have confirmed your mandate. Prior results don\'t guarantee a similar outcome.',
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '08:30', 'closes' => '18:30'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '08:30', 'closes' => '18:30'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '08:30', 'closes' => '18:30'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '08:30', 'closes' => '18:30'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '08:30', 'closes' => '16:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Clear counsel when the stakes are high',
                'subtitle' => 'Law firm in Frankfurt am Main',
                'text' => 'For companies, founders and private clients since 1998. Every matter is led by a partner, and you get an honest assessment of your chances from the first meeting.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'Practice areas', 'url' => '/practice-areas'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            $this->figures(),
            $this->practices(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'consultation' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## You speak with your attorney, not a call centre\n\nLarge firms pass matters down to junior staff. We don't. The partner who takes on your case knows every document, answers your calls and stands next to you in court.\n\n- **Honest assessment:** chances, risks and costs before you decide\n- **Fast response:** an appointment within two working days, urgent matters the same day\n- **Clear costs:** fixed fees or agreed caps wherever possible",
            ]],
            $this->recognition(),
            $this->team( 'Our partners', ['arndt', 'keller', 'weber', 'dubois'] ),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How we work with you',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Contact', 'text' => 'Online or by phone, we check for conflicts the same day.'],
                    ['label' => 'Step 2', 'title' => 'Consultation', 'text' => 'We assess your case and explain the options and costs.'],
                    ['label' => 'Step 3', 'title' => 'Strategy', 'text' => 'You decide, we put the agreed plan into writing.'],
                    ['label' => 'Step 4', 'title' => 'Resolution', 'text' => 'Negotiated, in court or by contract, with regular updates.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our clients say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Insights',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->insightsId, 'label' => 'Insights'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'What does a first consultation cost?', 'text' => 'For private clients, an initial consultation costs €190 plus VAT. If you instruct us afterwards, the fee is credited.'],
                    ['title' => 'How quickly can I get an appointment?', 'text' => 'Within two working days, in urgent cases such as a dismissal or an arrest on the same day.'],
                    ['title' => 'Can you advise me in English?', 'text' => 'Yes. All our attorneys work in German and English, several also in French, Polish or Turkish.'],
                    ['title' => 'What if I need help at night?', 'text' => 'For arrests, searches and other criminal law emergencies, our hotline +49 69 5550 4199 reaches a defense lawyer day and night.'],
                ],
            ]],
            $this->map( 'Visit us' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let\'s talk about your matter',
                'text' => 'Tell us what it is about. An attorney gets back to you within two working days, usually sooner.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'Call +49 69 5550 4100', 'url' => 'tel:+496955504100'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Arndt & Keller, law firm in Frankfurt am Main: corporate and M&A, employment, real estate, litigation, startups, family, inheritance and criminal law.',
                'keywords' => 'law firm Frankfurt, lawyer Frankfurt, attorney Frankfurt, employment lawyer, corporate lawyer, family lawyer, criminal defense lawyer',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Arndt & Keller | Law Firm in Frankfurt am Main',
                'description' => 'Clear counsel when the stakes are high, led by partners since 1998.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Arndt & Keller | Law Firm in Frankfurt am Main', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a brass line icon once and returns its file reference.
     *
     * @param string $name Icon name: award, book, globe, scale or shield
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
            'book' => '<path d="M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/><path d="M9 8h7"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18z"/>',
            'scale' => '<path d="M12 3v18"/><path d="M7 21h10"/><path d="M5 7h14"/><path d="m5 7-3 6a3 3 0 0 0 6 0z"/><path d="m19 7-3 6a3 3 0 0 0 6 0z"/>',
            'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#7D5F2A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Brass line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates an insight article below the insights page.
     *
     * @param Page $parent Insights page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Steps to take
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function insight( Page $parent, array $data, string $title, string $text, string $cover,
        array $facts, array $steps, array $questions ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article( $title, $text, $this->img( $cover ) ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'columns' => '3',
                'cards' => $facts,
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Step by step',
                'layout' => 'vertical',
                'items' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from our clients',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Every case is different',
                'text' => 'This article gives a general overview and can\'t replace advice on your situation. Book a consultation, and we look at your case.',
                'buttons' => [
                    ['label' => 'Book a consultation', 'url' => '/consultation'],
                    ['label' => 'Call +49 69 5550 4100', 'url' => 'tel:+496955504100'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Creates the insights overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Insights page
     */
    protected function insights( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->insightsId,
            'lang' => 'en',
            'name' => 'Insights',
            'title' => 'Legal Insights | Arndt & Keller Frankfurt',
            'path' => 'insights',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'German law, clearly explained',
                'subtitle' => 'Insights',
                'text' => 'Practical guides from our attorneys on the questions our clients ask most, from dismissals to shareholder agreements and wills.',
            ]],
            ['id' => 'insight-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->insightsId, 'label' => 'Insights'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates the Arndt & Keller SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Arndt &amp; Keller logo</title>
  <desc id="desc">Navy square with gold initials A and K beside the Arndt &amp; Keller wordmark</desc>
  <rect x="6" y="8" width="64" height="64" fill="#0E2A47"/>
  <rect x="11" y="13" width="54" height="54" fill="none" stroke="#C6A15B" stroke-width="1"/>
  <text x="38" y="51" fill="#C6A15B" text-anchor="middle" font-family="Baskerville, 'Iowan Old Style', 'Palatino Linotype', Georgia, serif" font-size="28">AK</text>
  <text x="88" y="44" fill="#0E2A47" font-family="Baskerville, 'Iowan Old Style', 'Palatino Linotype', Georgia, serif" font-size="32">Arndt <tspan fill="#7D5F2A" font-style="italic">&amp;</tspan> Keller</text>
  <text x="89" y="64" fill="#5B6573" font-family="system-ui, 'Segoe UI', Arial, sans-serif" font-size="11" letter-spacing="3.5">RECHTSANWÄLTE · FRANKFURT</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'arndt-keller-logo.svg',
                'Arndt & Keller logo',
                'Navy square with gold initials A and K beside the Arndt & Keller wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the map element with address, office hours and directions.
     *
     * @param string $title Map headline
     * @return array<string, mixed> Map content element
     */
    protected function map( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => "**Arndt & Keller Rechtsanwälte**\nBockenheimer Landstraße 51, 14th floor · 60325 Frankfurt am Main\n\n**Office hours**\nMonday to Thursday 08:30–18:30\nFriday 08:30–16:00\n\n**Call**\n+49 69 5550 4100\n\n**Criminal law hotline, day and night**\n+49 69 5550 4199\n\n**Getting here**\nU6 or U7 to Westend, two minutes on foot. Client parking in the underground garage.",
            'location' => [
                'latitude' => 50.1179,
                'longitude' => 8.6650,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Creates a Counsel demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->ids( $content )[0] ?? $this->file();

        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Arndt & Keller, law firm Frankfurt, lawyer Frankfurt, attorney, legal advice, Rechtsanwalt Frankfurt' );
    }


    /**
     * Builds the Counsel demo page tree.
     */
    protected function pages() : void
    {
        $this->insightsId = (string) Str::uuid7();
        $home = $this->home();

        $this->addPractice( $home )
            ->addAttorneys( $home )
            ->addResults( $home )
            ->addInsights( $home )
            ->addConsultation( $home )
            ->addCareers( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the practice areas card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function practices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Practice areas',
            'columns' => '4',
            'cards' => [
                ['title' => 'Corporate and M&A', 'text' => 'Formations, shareholder agreements, acquisitions and sales of companies.', 'url' => '/corporate-law', 'file' => ['id' => $this->img( 'towers' ), 'type' => 'file']],
                ['title' => 'Employment law', 'text' => 'Dismissals, severance and contracts for employees and employers.', 'url' => '/employment-law', 'file' => ['id' => $this->img( 'office-staff' ), 'type' => 'file']],
                ['title' => 'Real estate law', 'text' => 'Property purchases, commercial leases and construction contracts.', 'url' => '/real-estate-law', 'file' => ['id' => $this->img( 'keys' ), 'type' => 'file']],
                ['title' => 'Commercial litigation', 'text' => 'Contract disputes, injunctions and arbitration.', 'url' => '/litigation', 'file' => ['id' => $this->img( 'gavel' ), 'type' => 'file']],
                ['title' => 'Startups and venture', 'text' => 'GmbH formation, financing rounds and employee participation.', 'url' => '/startups-venture', 'file' => ['id' => $this->img( 'startup' ), 'type' => 'file']],
                ['title' => 'Family law', 'text' => 'Separation, divorce, custody and prenuptial agreements.', 'url' => '/family-law', 'file' => ['id' => $this->img( 'family' ), 'type' => 'file']],
                ['title' => 'Inheritance law', 'text' => 'Wills, compulsory portions and disputes among heirs.', 'url' => '/inheritance-law', 'file' => ['id' => $this->img( 'elderly-couple' ), 'type' => 'file']],
                ['title' => 'Criminal defense', 'text' => 'Investigations, searches and white-collar crime, 24/7.', 'url' => '/criminal-defense', 'file' => ['id' => $this->img( 'gavel-plain' ), 'type' => 'file']],
            ],
        ]];
    }


    /**
     * Returns the firm recognition element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function recognition() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Why clients choose us',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Partner-led', 'text' => 'A partner leads every matter personally', 'file' => $this->icon( 'scale' )],
                ['title' => '5 specialists', 'text' => 'Certified specialist lawyers (Fachanwälte)', 'file' => $this->icon( 'award' )],
                ['title' => 'Since 1998', 'text' => 'Rooted in Frankfurt for over 25 years', 'file' => $this->icon( 'book' )],
                ['title' => '5 languages', 'text' => 'German, English, French, Polish and Turkish', 'file' => $this->icon( 'globe' )],
                ['title' => 'Confidential', 'text' => 'Professional secrecy and data stored in Germany', 'file' => $this->icon( 'shield' )],
            ],
        ]];
    }


    /**
     * Returns the client reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Managing director', 'role' => 'Logistics group, Hesse', 'text' => 'Dr. Arndt guided us through the sale of our family business with great calm. She always knew which points mattered and which we could give up.'],
            ['name' => 'Sales director', 'role' => 'Employment law client', 'text' => 'After my dismissal, I felt powerless. Dr. Weber explained my options in the first meeting, and three months later I had a settlement I could live with.'],
            ['name' => 'Co-founder', 'role' => 'Fintech startup, Frankfurt', 'text' => 'Fixed fees, fast answers and no legalese. Amara Okafor got our seed round closed while we kept building the product.'],
        ];
    }


    /**
     * Returns a team card element with the given attorneys.
     *
     * @param string $title Element headline
     * @param array<int, string> $keys Attorney keys
     * @return array<string, mixed> Cards content element
     */
    protected function team( string $title, array $keys ) : array
    {
        $people = [
            'arndt' => ['Dr. Katharina Arndt', 'Founding partner. Corporate law and M&A, more than 120 transactions. Studied in Frankfurt and at Columbia Law School.'],
            'brenner' => ['Leon Brenner', 'Associate since 2021. Certified specialist in criminal law, focus on white-collar crime and tax offences.'],
            'dubois' => ['Claire Dubois', 'Partner since 2016. Certified specialist in tenancy and condominium law, real estate transactions. Advises in French.'],
            'keller' => ['Markus Keller', 'Founding partner. Commercial litigation and arbitration, more than 600 court cases. Lecturer at the Frankfurt bar.'],
            'lindner' => ['Sophie Lindner', 'Associate since 2020. Certified specialist in family law and trained mediator. Advises in English and Polish.'],
            'okafor' => ['Amara Okafor', 'Counsel since 2019. Startups and venture capital, previously at a Berlin venture fund. Advises in English and Igbo.'],
            'schreiber' => ['Daniel Schreiber', 'Associate since 2018. Certified specialist in inheritance law, business succession and estate disputes.'],
            'weber' => ['Dr. Jonas Weber', 'Partner since 2009. Certified specialist in employment law, for executives, employees and works councils.'],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => $title,
            'columns' => '4',
            'cards' => array_map( fn( $key ) => [
                'title' => $people[$key][0],
                'text' => $people[$key][1],
                'file' => ['id' => $this->img( 'portrait-' . $key ), 'type' => 'file'],
            ], $keys ),
        ]];
    }
}
