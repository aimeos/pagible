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
 * Medical theme demo for the fictional Lindenhof Dental practice.
 */
class MedicalDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'anxious-patients' => 'Dentist for anxious patients in Freiburg: longer appointments, explanations before every step, a stop signal and optional sedation.',
        'appointment' => 'Book an appointment at Lindenhof Dental in Freiburg online or by phone. New patients, all insurances and early morning appointments.',
        'childrens-dentistry' => 'Children\'s dentistry in Freiburg: playful first visits, fissure sealing and fluoride varnish in a practice that takes its time for children.',
        'clear-aligners' => 'Clear aligners at Lindenhof Dental in Freiburg: straighter teeth with nearly invisible trays, planned with a digital 3D scan.',
        'careers' => 'Jobs at Lindenhof Dental in Freiburg: dental assistants, a dental hygienist and apprentices in a calm, modern practice.',
        'cleaning-guide' => 'What happens during a professional teeth cleaning at Lindenhof Dental, how long it takes and how often it makes sense.',
        'crowns-bridges' => 'Ceramic crowns and bridges in Freiburg, designed from a digital scan and matched to the shade of your own teeth.',
        'dental-emergencies' => 'Dental emergency in Freiburg? Same-day appointments for toothache, broken or knocked-out teeth and an emergency number outside opening hours.',
        'first-visit-guide' => 'How to prepare your child for the first visit at the dentist, and what happens in the chair at Lindenhof Dental in Freiburg.',
        'fillings-root-canals' => 'Tooth-coloured fillings and root canal treatments in Freiburg, planned with digital X-rays and performed with local anaesthesia.',
        'guides' => 'Patient guides from Lindenhof Dental in Freiburg: what happens during cleanings, implant treatments and your child\'s first visit.',
        'imprint' => 'Legal notice of Lindenhof Dental, Freiburg im Breisgau.',
        'implant-guide' => 'A dental implant step by step: consultation, 3D planning, placement, healing and the final crown at Lindenhof Dental.',
        'implants' => 'Dental implants in Freiburg: 3D planning, gentle placement and crowns made to match your own teeth.',
        'patient-info' => 'Patient information from Lindenhof Dental in Freiburg: your first appointment, insurance and costs, emergencies and how to get here.',
        'prevention-cleaning' => 'Check-ups and professional teeth cleaning in Freiburg by our dental hygienist, for adults and children.',
        'privacy' => 'Privacy policy of Lindenhof Dental, Freiburg im Breisgau.',
        'team' => 'Meet the dentists, the dental hygienist and the assistants of Lindenhof Dental in Freiburg im Breisgau.',
        'teeth-whitening' => 'Professional teeth whitening at Lindenhof Dental in Freiburg, after a check-up and with gentle, tested gels.',
        'treatments' => 'Prevention, fillings, crowns, implants, clear aligners, children\'s dentistry, whitening and emergencies at Lindenhof Dental in Freiburg.',
    ];

    /**
     * Curated Unsplash photos used by the dental practice demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'aligners' => ['photo-1564420228450-d9a5bc8d6565', 'Clear aligners', 'Hand holding a transparent aligner tray'],
        'boy-chair' => ['photo-1653508310895-62141575a3a9', 'Child in the dental chair', 'Smiling boy sitting in a dental chair'],
        'boy-exam' => ['photo-1758205307836-0829c799890b', 'Child check-up', 'Dentist examining the teeth of a young boy'],
        'brushes' => ['photo-1607613009820-a29f7bb81c04', 'Toothbrushes', 'Bamboo toothbrushes standing in a glass jar'],
        'chair' => ['photo-1643660526741-094639fbe53a', 'Treatment room', 'Modern dental chair in a bright treatment room'],
        'chair-window' => ['photo-1728342057953-94bfad8f0e7e', 'Treatment room with a view', 'Dental chair next to a large window'],
        'checkup' => ['photo-1681939282781-341ac4f61996', 'Check-up', 'Woman lying in a dental chair during a check-up'],
        'consult' => ['photo-1777331903190-341a3dd0441b', 'Consultation', 'Dentist talking with a patient in a modern treatment room'],
        'dentist' => ['photo-1657470179447-0f5aa16daa91', 'Dentist at work', 'Dentist with mask and loupes treating a patient'],
        'girl-brush' => ['photo-1609840113564-ab4aba4956c4', 'Brushing teeth', 'Girl brushing her teeth and smiling'],
        'implant' => ['photo-1593022356769-11f762e25ed9', 'Implant model', 'Model of a jaw with a dental implant and crown'],
        'office' => ['photo-1629909613654-28e377c37b09', 'Our practice', 'Bright dental practice with a treatment chair and lamp'],
        'procedure' => ['photo-1588776814546-daab30f310ce', 'Treatment', 'Two dentists treating a patient together'],
        'smile' => ['photo-1617812191081-2a24e3f30e45', 'Bright smile', 'Smiling woman with bright teeth'],
        'smile-man' => ['photo-1489278353717-f64c6ee8a4d2', 'Smiling patient', 'Portrait of a smiling young man'],
        'smile-woman' => ['photo-1494790108377-be9c29b29330', 'Smiling patient', 'Portrait of a smiling young woman'],
        'team-anna' => ['photo-1594824476967-48c8b964273f', 'Dr. Anna Lindner', 'Dentist in teal scrubs smiling with crossed arms'],
        'team-felix' => ['photo-1622253692010-333f2da6031d', 'Felix Brandt', 'Dental hygienist in blue scrubs'],
        'team-jonas' => ['photo-1612349317150-e413f6a5b16d', 'Dr. Jonas Weber', 'Dentist in a white coat smiling'],
        'team-mira' => ['photo-1659353888906-adb3e0041693', 'Dr. Mira Hofmann', 'Dentist in a white coat in front of a red wall'],
        'team-talk' => ['photo-1758691463203-cce9d415b2b5', 'Our team', 'Two doctors in white coats discussing a treatment plan on a tablet'],
        'tools' => ['photo-1606811856475-5e6fcdc6e509', 'Instruments', 'Sterile dental instruments on a tray'],
        'waiting' => ['photo-1629909614456-6b1c5c94cecc', 'Waiting room', 'Waiting room with teal sofas and plants'],
        'xray' => ['photo-1588776814546-1ffcf47267a5', 'Digital X-ray', 'Dentist looking at an X-ray image on a screen'],
    ];

    private string $element;
    private string $guidesId;
    /** @var array<string, string> Icon file IDs keyed by name */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the appointment page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAppointment( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Appointment',
            'title' => 'Book an Appointment | Lindenhof Dental Freiburg',
            'path' => 'appointment',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => 'appointment', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Book an appointment',
                'description' => 'Tell us what you need and when you are available. We confirm your appointment by phone or email within one working day. If you are in pain, please call us, we keep slots free for acute cases every day.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Reason for your visit', 'required' => true, 'input' => 'select', 'options' => "Check-up\nProfessional cleaning\nToothache or a broken tooth\nFilling or crown\nImplant consultation\nClear aligners\nChildren's appointment\nTeeth whitening"],
                    ['field' => 'Patient', 'required' => true, 'input' => 'select', 'options' => "New patient\nExisting patient"],
                    ['field' => 'Insurance', 'required' => true, 'input' => 'select', 'options' => "Statutory\nPrivate\nSelf-pay"],
                    ['field' => 'Preferred time', 'required' => false, 'input' => 'select', 'options' => "Early morning\nMorning\nAfternoon\nEvening"],
                ],
            ]],
            $this->map( 'How to find us' ),
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
            'title' => 'Jobs at Lindenhof Dental Freiburg',
            'path' => 'careers',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Work where patients feel at ease',
                'subtitle' => 'Careers',
                'text' => 'We plan with enough time for every patient, and that applies to our team as well: no rushed appointments, no overtime as a rule and a team that has worked together for years.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@lindenhof-dental.example'],
                ],
                'background' => ['id' => $this->img( 'team-talk' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'procedure' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you can expect\n\n- A four-day week if you like, with fixed shifts planned months ahead\n- Pay above the collective agreement and a company pension\n- Paid further training, from prophylaxis to practice management\n- Modern equipment with digital X-rays, intraoral scanners and loupes\n- A job ticket for Freiburg's trams and buses",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Dental assistant (m/f/d)', 'text' => 'Full or part time. Chairside assistance, sterilisation and a friendly word for every patient.'],
                    ['title' => 'Dental hygienist (m/f/d)', 'text' => 'Part time, 20–30 hours. Professional cleanings and gum care with your own treatment room.'],
                    ['title' => 'Apprenticeship 2027 (m/f/d)', 'text' => 'Three years of training as a dental assistant, with a mentor at your side from day one.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Interested?',
                'text' => 'Send us a few lines about yourself and your CV. A cover letter is not necessary, and we reply within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@lindenhof-dental.example'],
                    ['label' => 'Meet the team', 'url' => '/team'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the patient guides page and its articles below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addGuides( Page $home ) : static
    {
        $guides = $this->guides( $home );

        $this->guide( $guides, [
            'name' => 'Professional cleaning',
            'title' => 'What Happens During a Professional Teeth Cleaning',
            'path' => 'cleaning-guide',
        ], 'Your professional cleaning, explained',
            "Even with careful brushing, plaque and tartar build up where the brush doesn't reach: between the teeth, along the gum line and behind the lower front teeth. A professional cleaning removes these deposits and gives your gums the chance to settle down.\n\nAt Lindenhof Dental, our dental hygienist takes about an hour for you. He checks your gums, removes tartar and discolouration, polishes your teeth and shows you which brush and which interdental brushes fit your teeth.",
            'checkup',
            [
                ['title' => '60 min', 'text' => 'Time our hygienist takes for you'],
                ['title' => '1–2×', 'text' => 'Per year for most adults'],
                ['title' => '€110', 'text' => 'Self-pay price, some insurers contribute'],
            ],
            [
                ['label' => 'Step 1', 'title' => 'Gum check', 'text' => 'We measure your gum pockets and note bleeding points.'],
                ['label' => 'Step 2', 'title' => 'Tartar removal', 'text' => 'Ultrasound and fine hand instruments remove hard deposits.'],
                ['label' => 'Step 3', 'title' => 'Polishing', 'text' => 'Powder jet and polishing paste smooth the tooth surfaces.'],
                ['label' => 'Step 4', 'title' => 'Fluoride and tips', 'text' => 'A protective varnish and advice for your daily care.'],
            ],
            [
                ['title' => 'Does a professional cleaning hurt?', 'text' => 'Most patients find it comfortable. If your teeth are sensitive, tell us beforehand and we use a numbing gel or a gentler method.'],
                ['title' => 'Does my insurance pay for it?', 'text' => 'Statutory insurance doesn\'t cover it as a standard benefit, but many insurers contribute a fixed amount per year. Private insurance usually pays depending on your plan.'],
                ['title' => 'Can I eat afterwards?', 'text' => 'Yes. We recommend waiting about 30 minutes after the fluoride varnish and avoiding strongly coloured food such as coffee or red wine for the rest of the day.'],
            ],
        );

        $this->guide( $guides, [
            'name' => 'Implants step by step',
            'title' => 'A Dental Implant Step by Step',
            'path' => 'implant-guide',
        ], 'From the gap to the new tooth',
            "An implant replaces the root of a missing tooth with a small titanium or ceramic screw. Once it has grown into the bone, it carries a crown that looks and feels like your own tooth and doesn't need the neighbouring teeth for support.\n\nThe whole treatment usually takes three to six months, most of it healing time. You are with us for a few short appointments, and you are never left with a visible gap in between.",
            'implant',
            [
                ['title' => '3D', 'text' => 'Planning with a low-dose digital volume scan'],
                ['title' => '3–6', 'text' => 'Months from the consultation to the crown'],
                ['title' => '5', 'text' => 'Appointments for most patients'],
            ],
            [
                ['label' => 'Week 1', 'title' => 'Consultation', 'text' => 'Examination, X-ray and a cost plan for you and your insurer.'],
                ['label' => 'Week 3', 'title' => '3D planning', 'text' => 'Volume scan and a digital plan for the exact implant position.'],
                ['label' => 'Week 5', 'title' => 'Placement', 'text' => 'The implant is placed under local anaesthesia in about an hour.'],
                ['label' => 'Months 2–4', 'title' => 'Healing', 'text' => 'The implant grows into the bone, a temporary tooth closes the gap.'],
                ['label' => 'Month 5', 'title' => 'Crown', 'text' => 'A digital scan and a ceramic crown matched to your own teeth.'],
            ],
            [
                ['title' => 'Is everyone suitable for an implant?', 'text' => 'Most adults are. Heavy smoking, untreated gum disease or some general illnesses can affect healing, so we discuss your medical history in detail before planning.'],
                ['title' => 'What if there isn\'t enough bone?', 'text' => 'In many cases the bone can be built up before or during the placement. Your 3D scan shows whether this is necessary.'],
                ['title' => 'How long does an implant last?', 'text' => 'With good care and regular check-ups, implants can last for many years. Like natural teeth, they need daily cleaning and a professional cleaning once or twice a year.'],
            ],
        );

        $this->guide( $guides, [
            'name' => 'Your child\'s first visit',
            'title' => 'Your Child\'s First Visit at the Dentist',
            'path' => 'first-visit-guide',
        ], 'A relaxed start for little teeth',
            "We recommend the first visit when the first tooth appears, and at the latest around the first birthday. The earlier children get to know the practice, the more natural the visits become, long before any treatment is needed.\n\nThe first appointment is mostly about getting to know each other. Your child can ride the chair up and down, count teeth with the mirror and take home a small present. You get tips on brushing, fluoride and drinking habits.",
            'boy-chair',
            [
                ['title' => '6–12', 'text' => 'Months, the right age for the first visit'],
                ['title' => '30 min', 'text' => 'Reserved for every first children\'s appointment'],
                ['title' => '€0', 'text' => 'Check-ups are covered by statutory insurance'],
            ],
            [
                ['label' => 'Before', 'title' => 'Talk about it', 'text' => 'Read a picture book about the dentist and avoid words like "pain" or "injection".'],
                ['label' => 'Arrival', 'title' => 'Play corner', 'text' => 'A few minutes in our waiting room to get used to the place.'],
                ['label' => 'In the chair', 'title' => 'Counting teeth', 'text' => 'Your child sits on your lap or alone, as it prefers.'],
                ['label' => 'After', 'title' => 'Brushing tips', 'text' => 'Toothbrush, toothpaste and a small reward for the way home.'],
            ],
            [
                ['title' => 'What if my child doesn\'t want to open the mouth?', 'text' => 'That\'s completely fine. We never force anything and simply try again at the next visit. Trust is more important than a complete examination.'],
                ['title' => 'How often should children come?', 'text' => 'Every six months. Between the ages of six and seventeen, statutory insurance also pays for additional preventive care such as fissure sealing.'],
                ['title' => 'Can siblings come along?', 'text' => 'Of course. We can also book appointments for siblings back to back, so you only come once.'],
            ],
        );

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
            'title' => 'Imprint | Lindenhof Dental',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Lindenhof Dental**\nDr. Anna Lindner and Dr. Jonas Weber, dentists, partnership\nLindenstraße 8\n79098 Freiburg im Breisgau\nGermany\n\nTelephone: 0761 4567 230\nEmail: hello@lindenhof-dental.example\n\nProfessional title: Zahnarzt/Zahnärztin, awarded in the Federal Republic of Germany\nCompetent chamber: Landeszahnärztekammer Baden-Württemberg\nCompetent association: Kassenzahnärztliche Vereinigung Baden-Württemberg\nProfessional regulations: Berufsordnung der Landeszahnärztekammer Baden-Württemberg\n\nThis is a demo website for the Medical theme. Lindenhof Dental is a fictional practice, the people shown are not real dentists.",
            ]],
        ], $home );

        return $this;
    }

    /**
     * Creates the patient information page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPatientInfo( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Patient info',
            'title' => 'Patient Information | Lindenhof Dental Freiburg',
            'path' => 'patient-info',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything for your visit',
                'subtitle' => 'Patient info',
                'text' => 'What to bring to your first appointment, how insurance and costs work and what to do if a tooth suddenly hurts.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                ],
                'background' => ['id' => $this->img( 'waiting' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '3',
                'cards' => [
                    ['title' => 'Your first appointment', 'text' => 'Please bring your insurance card, a list of the medicines you take and your allergy or implant pass. You receive the medical history form by email with your confirmation, so you can fill it in at home.', 'file' => ['id' => $this->img( 'consult' ), 'type' => 'file']],
                    ['title' => 'Insurance and costs', 'text' => 'We treat statutory, private and self-paying patients. Before any treatment your insurance doesn\'t fully cover, you get a written cost plan, and larger treatments can be paid in instalments.', 'file' => ['id' => $this->img( 'tools' ), 'type' => 'file']],
                    ['title' => 'Dental emergencies', 'text' => 'Toothache, a broken or knocked-out tooth? Call us, we keep slots free every day. Outside our opening hours, call our emergency number 0761 4567 299.', 'url' => '/dental-emergencies', 'file' => ['id' => $this->img( 'xray' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'office' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Getting here\n\nThe practice is on the second floor of Lindenstraße 8, right next to the Stadtgarten.\n\n- **Tram:** lines 1, 3 and 5 to Stadtgarten, two minutes on foot\n- **Car:** Schlossberg car park, 300 metres away, two reserved parking spaces in the courtyard for patients with reduced mobility\n- **Step-free:** ramp at the entrance, lift to the second floor and an accessible toilet\n\nPlease cancel appointments you can't keep at least 24 hours in advance, so another patient can take your slot.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do I need a referral?', 'text' => 'No. You can come to us directly with any insurance. If your previous dentist has recent X-rays, ask them to send them to us, so you don\'t need new ones.'],
                    ['title' => 'Does supplementary dental insurance make sense?', 'text' => 'For crowns, implants and professional cleanings it often does. Bring your policy to the consultation and we include the expected refund in your cost plan.'],
                    ['title' => 'Can I pay by card?', 'text' => 'Yes, at reception by debit or credit card. Larger self-pay amounts are invoiced after the treatment and can be paid in monthly instalments.'],
                    ['title' => 'Can someone come with me?', 'text' => 'Of course. A person you trust can sit with you in the treatment room, and parents always stay with their children.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Still a question open?',
                'text' => 'Our reception team is happy to help from Monday to Thursday 07:30–19:00 and on Friday 07:30–14:00.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'Call 0761 4567 230', 'url' => 'tel:+497614567230'],
                ],
            ]],
        ], $home );

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
            'title' => 'Privacy Policy | Lindenhof Dental',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nLindenhof Dental, Dr. Anna Lindner and Dr. Jonas Weber, Lindenstraße 8, 79098 Freiburg im Breisgau, hello@lindenhof-dental.example.\n\n## Appointment requests\n\nWhen you send the appointment form, we use your name, phone number, email address and the details you enter only to plan and confirm your appointment (Art. 6 (1) (b) and Art. 9 (2) (h) GDPR). Requests that don't lead to a treatment are deleted after six months.\n\n## Patient records\n\nAs your dentist, we are legally required to keep treatment records for ten years. They are stored in our practice software in Germany and are only accessible to our team. We share data with your insurer, dental laboratory or other doctors only as far as necessary for your treatment and billing.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the data protection authority of Baden-Württemberg.\n\nThis is a demo website for the Medical theme. Lindenhof Dental is a fictional practice.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the team page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTeam( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Team',
            'title' => 'Our Team | Lindenhof Dental Freiburg',
            'path' => 'team',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'People who take their time',
                'subtitle' => 'Our team',
                'text' => 'Three dentists, a dental hygienist and eight dental assistants who know your name and your history.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                ],
                'background' => ['id' => $this->img( 'team-talk' ), 'type' => 'file'],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'waiting' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## A practice built around patients\n\nDr. Anna Lindner opened the practice in 2001 in an old townhouse at the Lindenhof. In 2014, Dr. Jonas Weber joined as a partner and the practice moved to its current, step-free rooms with five treatment rooms and a separate children's room.\n\nWe plan our appointments with enough time, explain every step before we start and give you a written cost plan for anything your insurance doesn't fully cover.",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Our practice',
                'files' => array_map( fn( $key ) => ['id' => $this->cropped( $key, 1500, 1000 ), 'type' => 'file'], ['office', 'chair', 'tools', 'xray'] ),
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Qualified and certified',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'Dental chamber', 'text' => 'Members of the state dental chamber', 'file' => $this->icon( 'award' )],
                    ['title' => 'Implantology', 'text' => 'Certified by the national implantology society', 'file' => $this->icon( 'award' )],
                    ['title' => 'Children\'s dentistry', 'text' => 'Member of the paediatric dentistry society', 'file' => $this->icon( 'award' )],
                    ['title' => 'Hygiene', 'text' => 'Validated sterilisation, inspected every year', 'file' => $this->icon( 'insurance' )],
                    ['title' => 'Quality management', 'text' => 'Certified practice processes since 2012', 'file' => $this->icon( 'insurance' )],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our patients say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Join our team',
                'text' => 'We are looking for dental assistants, a dental hygienist and apprentices who share our calm way of working.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the treatments page and the treatment pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTreatments( Page $home ) : static
    {
        $treatments = $this->page( [
            'lang' => 'en',
            'name' => 'Treatments',
            'title' => 'Dental Treatments in Freiburg | Lindenhof Dental',
            'path' => 'treatments',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Everything for healthy teeth',
                'subtitle' => 'Our treatments',
                'text' => 'From the first children\'s visit to implants: modern dentistry with digital X-rays, 3D scans and enough time for your questions.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                ],
            ]],
            $this->treatments(),
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you accept new patients?', 'text' => 'Yes. New patients usually get a first appointment within two weeks. If you are in pain, call us and we see you on the same day.'],
                    ['title' => 'Which insurances do you accept?', 'text' => 'We treat patients with statutory and private insurance as well as self-paying patients. For treatments that aren\'t fully covered, you get a written cost plan before we start.'],
                    ['title' => 'I\'m afraid of the dentist. What can I do?', 'text' => 'Tell us when you book. We plan a longer first appointment without any treatment, explain every step and agree on a stop signal. Many anxious patients come to us regularly today.'],
                    ['title' => 'Do you have early or late appointments?', 'text' => 'Yes. From Monday to Thursday our first appointments start at 07:30 and the last ones at 18:30.'],
                ],
            ]],
        ], $home );

        foreach( $this->offers() as $offer ) {
            $this->treatment( $treatments, ...$offer );
        }

        return $this;
    }


    /**
     * Returns the practice highlights element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function badges() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Good to know',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'New patients welcome', 'text' => 'First appointment within two weeks', 'file' => $this->icon( 'patient' )],
                ['title' => 'All insurances', 'text' => 'Statutory, private and self-paying patients', 'file' => $this->icon( 'insurance' )],
                ['title' => 'Step-free access', 'text' => 'Lift to the second floor and a wide treatment room', 'file' => $this->icon( 'lift' )],
                ['title' => 'Early and late', 'text' => 'Appointments from 07:30 to 18:30', 'file' => $this->icon( 'clock' )],
                ['title' => 'We speak your language', 'text' => 'English, German, French and Turkish', 'file' => $this->icon( 'language' )],
            ],
        ]];
    }


    /**
     * Creates the shared Lindenhof footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Lindenhof footer', ['columns' => '4', 'cards' => [
            ['title' => 'Opening hours', 'text' => "- Mon–Thu: 07:30–19:00\n- Fri: 07:30–14:00\n- Emergencies: [0761 4567 299](tel:+497614567299)"],
            ['title' => 'Treatments', 'text' => "- [Prevention and cleaning](/prevention-cleaning)\n- [Implants](/implants)\n- [Clear aligners](/clear-aligners)\n- [Children's dentistry](/childrens-dentistry)\n- [Anxious patients](/anxious-patients)\n- [Dental emergencies](/dental-emergencies)"],
            ['title' => 'Practice', 'text' => "- [Our team](/team)\n- [Patient info](/patient-info)\n- [Patient guides](/guides)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Lindenstraße 8\n79098 Freiburg im Breisgau\n\n0761 4567 230\n[Book an appointment](/appointment)"],
        ]] );
    }


    /**
     * Returns the ID of the primary practice image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'consult' );
    }


    /**
     * Creates a patient guide below the guides page.
     *
     * @param Page $parent Guides page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Treatment steps
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function guide( Page $parent, array $data, string $title, string $text, string $cover,
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
                'title' => 'Questions from our patients',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Any questions left?',
                'text' => 'We are happy to talk them through with you in person. Book an appointment online or call us.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'Call 0761 4567 230', 'url' => 'tel:+497614567230'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Creates the patient guides overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Guides page
     */
    protected function guides( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->guidesId,
            'lang' => 'en',
            'name' => 'Patient guides',
            'title' => 'Patient Guides | Lindenhof Dental',
            'path' => 'guides',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Know what to expect',
                'subtitle' => 'Patient guides',
                'text' => 'Plain explanations of common treatments, so you can come to your appointment prepared and relaxed.',
            ]],
            ['id' => 'guide-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->guidesId, 'label' => 'Patient guides'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Creates a teal line icon once and returns its file reference.
     *
     * @param string $name Icon name: award, clock, insurance, language, lift or patient
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'insurance' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'language' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
            'lift' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="m9 10 3-3 3 3M9 14l3 3 3-3"/>',
            'patient' => '<circle cx="10" cy="8" r="4"/><path d="M3 21c0-4 3-7 7-7s7 3 7 7M19 8v6M16 11h6"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#0A7782" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Teal line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Lindenhof home page and returns it.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Lindenhof Dental'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'medical::practice' => [
                'type' => 'medical::practice',
                'files' => [],
                'data' => [
                    'name' => 'Lindenhof Dental',
                    'business-type' => 'Dentist',
                    'specialty' => 'Dentistry',
                    'street-address' => 'Lindenstraße 8',
                    'postal-code' => '79098',
                    'locality' => 'Freiburg im Breisgau',
                    'country' => 'DE',
                    'telephone' => '+49 761 4567 230',
                    'email' => 'hello@lindenhof-dental.example',
                    'emergency-phone' => '+49 761 4567 299',
                    'booking' => '/appointment',
                    'languages' => 'English, German, French, Turkish',
                    'new-patients' => true,
                    'price-range' => '€€',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '07:30', 'closes' => '19:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '07:30', 'closes' => '19:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '07:30', 'closes' => '19:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '07:30', 'closes' => '19:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '07:30', 'closes' => '14:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Relaxed visits for healthy teeth',
                'subtitle' => 'Your dental practice in Freiburg',
                'text' => "Modern dentistry for the whole family, with enough time to explain every step and appointments from 07:30 in the morning.\n\n★★★★★ Rated 4.8 out of 5 by 512 patients",
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'Our treatments', 'url' => '/treatments'],
                ],
                'background' => ['id' => $fileId, 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '25 years', 'text' => 'Caring for families in Freiburg'],
                    ['title' => '12', 'text' => 'People in our practice team'],
                    ['title' => '< 10 min', 'text' => 'Average time in the waiting room'],
                    ['title' => '4.8/5', 'text' => 'From 512 patient reviews'],
                ],
            ]],
            $this->treatments(),
            $this->badges(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'chair-window' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## A practice that feels calm\n\nBright rooms, short waiting times and a team that explains before it treats. Many of our patients came to us because they were nervous about the dentist and stayed because they no longer are.\n\nDigital X-rays with a low dose, 3D scans instead of impression trays and written cost plans make every treatment easy to follow.",
            ]],
            $this->prices(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your first visit',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Book', 'text' => 'Online or by phone, usually within two weeks.'],
                    ['label' => 'Step 2', 'title' => 'Get to know us', 'text' => 'Your medical history, your wishes and your questions.'],
                    ['label' => 'Step 3', 'title' => 'Examination', 'text' => 'Teeth, gums and a low-dose X-ray if needed.'],
                    ['label' => 'Step 4', 'title' => 'Your plan', 'text' => 'Clear options with a written cost plan, no pressure.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What our patients say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Patient guides',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->guidesId, 'label' => 'Patient guides'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you accept new patients?', 'text' => 'Yes. New patients usually get a first appointment within two weeks, and if you are in pain, we see you on the same day.'],
                    ['title' => 'What should I bring to my first visit?', 'text' => 'Your insurance card, a list of your medicines and your allergy or implant pass. You can fill in the medical history form at home, we email it with your confirmation.'],
                    ['title' => 'What does my insurance pay?', 'text' => 'Statutory insurance covers check-ups and necessary treatments. For anything it doesn\'t fully cover, you get a written cost plan before we start.'],
                    ['title' => 'What if I have a dental emergency at the weekend?', 'text' => 'Call our emergency number 0761 4567 299. It connects you to the dentist on call in Freiburg.'],
                ],
            ]],
            $this->map( 'Visit us' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Time for a check-up?',
                'text' => 'Book your appointment online in two minutes. New patients are welcome, and if you are in pain, we see you on the same day.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'Call 0761 4567 230', 'url' => 'tel:+497614567230'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Lindenhof Dental in Freiburg: check-ups, professional cleaning, fillings, implants, clear aligners and children\'s dentistry, with appointments from 07:30.',
                'keywords' => 'dentist Freiburg, dental practice, professional teeth cleaning, dental implants, clear aligners, children\'s dentist, teeth whitening',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Lindenhof Dental | Your Dentist in Freiburg',
                'description' => 'Modern dentistry for the whole family, with enough time to explain every step.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Lindenhof Dental | Your Dentist in Freiburg im Breisgau', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates the Lindenhof SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Lindenhof Dental logo</title>
  <desc id="desc">Teal rounded square with a white tooth and a mint plus beside the Lindenhof wordmark</desc>
  <rect x="4" y="8" width="64" height="64" rx="16" fill="#0A7782"/>
  <path d="M36 24C32 21 26 21 24 24C21 28 22 34 23 39C24 45 25 50 27 55C28 58 31 58 32 55L34 48C35 45 37 45 38 48L40 55C41 58 44 58 45 55C47 50 48 45 49 39C50 34 51 28 48 24C46 21 40 21 36 24Z" fill="#FFFFFF"/>
  <path d="M56 14v10M51 19h10" stroke="#8DD8C8" stroke-width="3.5" stroke-linecap="round"/>
  <text x="84" y="54" fill="#0F2D3A" font-family="'Avenir Next', 'Segoe UI', 'Helvetica Neue', system-ui, sans-serif" font-size="36" font-weight="700" letter-spacing="0.5">Lindenhof</text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'lindenhof-logo.svg',
                'Lindenhof Dental logo',
                'Teal rounded square with a white tooth and a mint plus beside the Lindenhof wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the map element with address, opening hours and directions.
     *
     * @param string $title Map headline
     * @return array<string, mixed> Map content element
     */
    protected function map( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => "**Lindenhof Dental**\nLindenstraße 8 · 79098 Freiburg im Breisgau\n\n**Opening hours**\nMonday to Thursday 07:30–19:00\nFriday 07:30–14:00\n\n**Call**\n0761 4567 230\n\n**Emergencies outside opening hours**\n0761 4567 299\n\n**Getting here**\nTram lines 1, 3 and 5 to Stadtgarten, step-free entrance and lift to the second floor.",
            'location' => [
                'latitude' => 47.9990,
                'longitude' => 7.8490,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Returns the arguments for the treatment pages.
     *
     * @return array<int, array<int, mixed>> Page data, headline, hero and image keys, text, included items and questions
     */
    protected function offers() : array
    {
        return [
            [['name' => 'Prevention and cleaning', 'title' => 'Check-ups and Professional Teeth Cleaning in Freiburg | Lindenhof Dental', 'path' => 'prevention-cleaning'],
                'Prevention is the best treatment', 'checkup', 'brushes',
                "## Regular check-ups, fewer surprises\n\nSmall problems are easy to treat when they are found early. At your check-up, we examine your teeth, gums and fillings, and look for signs of tooth grinding or gum disease.\n\nOur dental hygienist [Felix Brandt](/team) takes about an hour for your professional cleaning. He removes tartar and discolouration, polishes your teeth and shows you which tools make your daily care easier.\n\nRead [what happens during a professional teeth cleaning](/cleaning-guide) before your first appointment.",
                [
                    ['title' => 'Check-up', 'text' => 'Teeth, gums, fillings and the soft tissue of the mouth, twice a year.'],
                    ['title' => 'Professional cleaning', 'text' => 'Tartar removal, polishing and fluoride varnish in about an hour.'],
                    ['title' => 'Gum care', 'text' => 'Early treatment of inflamed gums before they become a problem.'],
                ],
                [
                    ['title' => 'What does a professional cleaning cost?', 'text' => 'A cleaning costs €110 for self-paying patients. Many statutory insurers contribute a fixed amount per year, private insurance usually pays depending on your plan.'],
                    ['title' => 'How often should I come?', 'text' => 'For most adults, a check-up every six months and a professional cleaning once or twice a year. With gum disease, we recommend shorter intervals.'],
                ]],
            [['name' => 'Fillings and root canals', 'title' => 'Fillings and Root Canal Treatment in Freiburg | Lindenhof Dental', 'path' => 'fillings-root-canals'],
                'Saving your own teeth', 'dentist', 'xray',
                "## Gentle treatment, lasting results\n\nWe fill cavities with tooth-coloured composite that is bonded to the tooth and blends in with its natural shade. Larger defects get a ceramic inlay that we design from a digital scan.\n\nIf the nerve of a tooth is inflamed, a root canal treatment can often save it. We work under local anaesthesia with magnifying loupes and fine instruments, and check the result with a low-dose digital X-ray.\n\nRoot canal treatments are performed by [Dr. Jonas Weber](/team), who has specialised in endodontics for more than ten years.",
                [
                    ['title' => 'Composite fillings', 'text' => 'Tooth-coloured, mercury-free and matched to your shade.'],
                    ['title' => 'Ceramic inlays', 'text' => 'For larger defects, designed from a digital scan.'],
                    ['title' => 'Root canal treatment', 'text' => 'With loupes, fine instruments and a digital X-ray check.'],
                ],
                [
                    ['title' => 'Does statutory insurance pay for fillings?', 'text' => 'Yes, for a standard filling. For composite in the back teeth or ceramic inlays, you pay the difference, and you get a written cost plan beforehand.'],
                    ['title' => 'Does a root canal treatment hurt?', 'text' => 'No more than a filling. Under local anaesthesia, most patients feel only pressure, and the pain of the inflamed nerve stops after the treatment.'],
                    ['title' => 'How many appointments do I need?', 'text' => 'A filling takes one appointment. A root canal treatment usually takes two to three appointments of about an hour.'],
                ]],
            [['name' => 'Implants', 'title' => 'Dental Implants in Freiburg | Lindenhof Dental', 'path' => 'implants'],
                'A firm tooth for every gap', 'procedure', 'implant',
                "## Planned in 3D, placed with care\n\nAn implant replaces the root of a missing tooth and carries a crown that looks and feels like your own tooth. The neighbouring teeth stay untouched.\n\nBefore we start, a low-dose 3D scan shows the bone and nerves exactly. You get a written plan with all steps and costs, and a temporary tooth means you never have to live with a visible gap.\n\n[Dr. Jonas Weber](/team) plans and places our implants. Read the [dental implant guide](/implant-guide) to see every step from the consultation to the crown.",
                [
                    ['title' => '3D planning', 'text' => 'Exact implant position from a low-dose volume scan.'],
                    ['title' => 'Local anaesthesia', 'text' => 'Most placements take about an hour, with optional sedation.'],
                    ['title' => 'Ceramic crown', 'text' => 'Matched to the shape and shade of your own teeth.'],
                ],
                [
                    ['title' => 'What does an implant cost?', 'text' => 'A single implant with a ceramic crown typically costs between €2,500 and €3,500. Statutory insurance pays a fixed subsidy, and you get a written cost plan to submit to your insurer.'],
                    ['title' => 'Am I too old for an implant?', 'text' => 'Age alone is not a reason against an implant. What matters is your general health and the bone, which we check with the 3D scan.'],
                    ['title' => 'Is the placement painful?', 'text' => 'Under local anaesthesia you feel only pressure. Afterwards, most patients need a painkiller for one or two days.'],
                ]],
            [['name' => 'Clear aligners', 'title' => 'Clear Aligners for Adults in Freiburg | Lindenhof Dental', 'path' => 'clear-aligners'],
                'Straighter teeth, hardly visible', 'smile-woman', 'aligners',
                "## Planned digitally, worn discreetly\n\nClear aligners move your teeth step by step with thin, transparent trays. You take them out to eat and brush, so your daily routine hardly changes.\n\nA digital scan replaces the impression tray and shows you a simulation of the planned result before you decide. During the treatment, you come in every six to eight weeks for a short check.\n\nYour aligner treatment is planned and checked by [Dr. Mira Hofmann](/team).",
                [
                    ['title' => '3D scan', 'text' => 'No impression trays, and a preview of the planned result.'],
                    ['title' => 'Nearly invisible', 'text' => 'Thin trays you wear about 22 hours a day.'],
                    ['title' => 'Retainer', 'text' => 'A fine wire or a night tray keeps the result in place.'],
                ],
                [
                    ['title' => 'What do clear aligners cost?', 'text' => 'A complete treatment for mild to moderate misalignment starts at €3,200. Statutory insurance doesn\'t pay for adults, private insurance sometimes contributes.'],
                    ['title' => 'How long does the treatment take?', 'text' => 'Usually six to eighteen months, depending on how far your teeth need to move. The simulation shows you the expected duration before you start.'],
                ]],
            [['name' => 'Children\'s dentistry', 'title' => 'Children\'s Dentist in Freiburg | Lindenhof Dental', 'path' => 'childrens-dentistry'],
                'Little teeth, big smiles', 'boy-exam', 'girl-brush',
                "## A practice children like to visit\n\nOur children's room has a ceiling full of stars, a chair that rides up and down and a dentist who explains everything in words children understand. We take our time, and nothing happens without your child being ready.\n\nFrom the first tooth, we look after healthy development with check-ups, fluoride varnish and fissure sealing of the back teeth. Parents get practical tips for brushing and healthy drinking.\n\n[Dr. Mira Hofmann](/team) looks after our youngest patients. Read [how to prepare your child for the first visit](/first-visit-guide).",
                [
                    ['title' => 'First visit', 'text' => 'Getting to know the practice in a relaxed, playful way.'],
                    ['title' => 'Fissure sealing', 'text' => 'Protects the grooves of the back teeth against decay.'],
                    ['title' => 'Brushing school', 'text' => 'Practical tips for children and parents.'],
                ],
                [
                    ['title' => 'When should my child first see a dentist?', 'text' => 'When the first tooth appears, and at the latest around the first birthday.'],
                    ['title' => 'What does insurance pay for children?', 'text' => 'Statutory insurance covers check-ups, fluoride varnish and fissure sealing of the back teeth for children and teenagers.'],
                ]],
            [['name' => 'Crowns and bridges', 'title' => 'Crowns and Bridges in Freiburg | Lindenhof Dental', 'path' => 'crowns-bridges'],
                'Strong teeth that look like your own', 'office', 'tools',
                "## Ceramic, designed digitally\n\nA crown protects a tooth that is too damaged for a filling, a bridge closes a gap by resting on the neighbouring teeth. Both are made of high-strength ceramic and matched to the shape and shade of your own teeth.\n\nInstead of impression trays, a digital scan captures your teeth in a few minutes. A temporary crown protects your tooth until the final one is ready, usually within a week.\n\nCrowns and bridges are planned by [Dr. Anna Lindner](/team) and [Dr. Jonas Weber](/team).",
                [
                    ['title' => 'Full ceramic crowns', 'text' => 'Metal-free, natural looking and very durable.'],
                    ['title' => 'Bridges', 'text' => 'Close a gap of one or two teeth without surgery.'],
                    ['title' => 'Digital scan', 'text' => 'No impression trays and a precise fit.'],
                ],
                [
                    ['title' => 'What does a crown cost?', 'text' => 'Statutory insurance pays a fixed subsidy for the standard solution. For a full ceramic crown, your share is usually between €400 and €700, shown in your written cost plan.'],
                    ['title' => 'Crown, bridge or implant?', 'text' => 'It depends on your neighbouring teeth and the bone. We show you all options with their pros, cons and costs, and you decide.'],
                ]],
            [['name' => 'Anxious patients', 'title' => 'Dentist for Anxious Patients in Freiburg | Lindenhof Dental', 'path' => 'anxious-patients'],
                'No more fear of the dentist', 'chair-window', 'consult',
                "## Treatment at your pace\n\nMany of our patients avoided the dentist for years. We don't judge, we listen. Your first appointment is only a conversation, with no instruments and no treatment unless you want it.\n\nBefore every step, we explain what happens and agree on a stop signal you can use at any time. For longer treatments, we offer nitrous oxide or sedation by an anaesthetist.\n\n[Dr. Anna Lindner](/team) has a certificate in dental hypnosis and looks after anxious patients herself.",
                [
                    ['title' => 'Talk first', 'text' => 'A longer first appointment just to get to know each other.'],
                    ['title' => 'Stop signal', 'text' => 'You raise your hand and we pause immediately.'],
                    ['title' => 'Sedation', 'text' => 'Nitrous oxide or sedation for longer treatments.'],
                ],
                [
                    ['title' => 'I haven\'t been to a dentist for years. Is that a problem?', 'text' => 'Not at all. We start with what matters most to you and plan the rest step by step, without pressure.'],
                    ['title' => 'Does insurance pay for sedation?', 'text' => 'Nitrous oxide is a self-pay service of about €60 per appointment. Sedation is covered in some cases, we check this with your insurer beforehand.'],
                ]],
            [['name' => 'Dental emergencies', 'title' => 'Dental Emergencies in Freiburg | Lindenhof Dental', 'path' => 'dental-emergencies'],
                'Toothache? We see you today', 'xray', 'chair',
                "## Help on the same day\n\nWe keep appointments free every day for patients in pain, including new patients. Call us in the morning and we find a time for you on the same day.\n\nOutside our opening hours, call our emergency number **0761 4567 299**. It connects you to the dentist on call in Freiburg.\n\n## What you can do until then\n\n- **Knocked-out tooth:** hold it by the crown, put it in cold milk and come immediately\n- **Swelling:** cool from the outside, but don't apply heat\n- **Broken tooth:** keep the pieces and rinse your mouth with water",
                [
                    ['title' => 'Same-day slots', 'text' => 'Free appointments for acute pain every day.'],
                    ['title' => 'Pain relief', 'text' => 'We stop the pain first and plan the rest calmly.'],
                    ['title' => 'Accidents', 'text' => 'Broken or knocked-out teeth, also for children.'],
                ],
                [
                    ['title' => 'Do you also treat emergencies for new patients?', 'text' => 'Yes. Please bring your insurance card, we take care of the paperwork after the treatment.'],
                    ['title' => 'What counts as an emergency?', 'text' => 'Severe toothache, swelling, bleeding after an extraction, an accident or a broken tooth. If you are unsure, just call us.'],
                ]],
            [['name' => 'Teeth whitening', 'title' => 'Professional Teeth Whitening in Freiburg | Lindenhof Dental', 'path' => 'teeth-whitening'],
                'A brighter shade of you', 'smile', 'smile-man',
                "## Safe whitening after a check-up\n\nCoffee, tea and time leave their colour on the teeth. Professional whitening can lighten your natural shade by several levels, using tested gels under dental supervision.\n\nWe always start with a check-up and a professional cleaning, because fillings and crowns don't change colour and cavities should be treated first. You choose between whitening in the practice and a home kit with custom-made trays.\n\nWhitening is done by [Felix Brandt](/team) after a check-up by one of our dentists.",
                [
                    ['title' => 'Check-up first', 'text' => 'Healthy teeth and gums are the basis for whitening.'],
                    ['title' => 'In the practice', 'text' => 'One appointment of about 90 minutes with a protected gum line.'],
                    ['title' => 'Home kit', 'text' => 'Custom trays and a gentle gel for about two weeks.'],
                ],
                [
                    ['title' => 'What does whitening cost?', 'text' => 'In-practice whitening costs €390, the home kit with custom trays €290. Insurance doesn\'t pay for whitening.'],
                    ['title' => 'How long does the result last?', 'text' => 'Usually one to three years, depending on how much coffee, tea or red wine you drink. A short home refresh keeps the shade bright.'],
                    ['title' => 'Does whitening damage the teeth?', 'text' => 'No. Tested gels under dental supervision don\'t harm the enamel. Your teeth may be sensitive for a few days afterwards.'],
                ]],
        ];
    }


    /**
     * Creates a Medical demo page below the given parent and returns it.
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

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Lindenhof Dental, dentist Freiburg, dental practice, teeth cleaning, implants, clear aligners, children\'s dentist' );
    }


    /**
     * Builds the Medical demo page tree.
     */
    protected function pages() : void
    {
        $this->guidesId = (string) Str::uuid7();
        $home = $this->home();

        $this->addTreatments( $home )
            ->addTeam( $home )
            ->addPatientInfo( $home )
            ->addGuides( $home )
            ->addAppointment( $home )
            ->addCareers( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the self-pay price list element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function prices() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Self-pay services',
            'text' => 'Typical prices for services statutory insurance doesn\'t fully cover. You always get a written cost plan before the treatment.',
            'items' => [
                [
                    'name' => 'Professional cleaning',
                    'prices' => [['id' => 'cleaning', 'amount' => 110, 'label' => '€110']],
                    'text' => 'About an hour with our dental hygienist.',
                    'features' => "- Tartar and discolouration removed\n- Polishing and fluoride varnish\n- Personal care tips",
                    'url' => '/prevention-cleaning',
                    'button' => 'Prevention and cleaning',
                    'highlight' => true,
                    'badge' => 'Most booked',
                ],
                [
                    'name' => 'Teeth whitening',
                    'prices' => [['id' => 'whitening', 'amount' => 390, 'label' => '€390']],
                    'text' => 'In-practice whitening after a check-up.',
                    'features' => "- Shade check before and after\n- Protected gum line\n- One appointment of about 90 minutes",
                    'url' => '/teeth-whitening',
                    'button' => 'Teeth whitening',
                ],
                [
                    'name' => 'Clear aligners',
                    'prices' => [['id' => 'aligners', 'amount' => 3200, 'label' => 'from €3,200']],
                    'text' => 'Complete treatment for mild to moderate misalignment.',
                    'features' => "- 3D scan and result preview\n- All aligner trays and checks\n- Retainer included",
                    'url' => '/clear-aligners',
                    'button' => 'Clear aligners',
                ],
            ],
        ]];
    }


    /**
     * Returns the patient reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Katrin M.', 'role' => 'Patient since 2016', 'text' => 'I avoided the dentist for years. Here, nobody judged me, everything was explained before it happened, and today I come for my cleaning without any worries.'],
            ['name' => 'Daniel and Leonie S.', 'role' => 'Parents of two', 'text' => 'Our daughter asks when she can see "her" dentist again. The children\'s room and the patience of the team make all the difference.'],
            ['name' => 'Ahmet Y.', 'role' => 'Implant patient', 'text' => 'The 3D planning and the written cost plan made the decision easy. The implant was placed in less than an hour, and I never had a gap in between.'],
        ];
    }


    /**
     * Returns the team cards element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet the team',
            'columns' => '4',
            'cards' => [
                ['title' => 'Dr. Anna Lindner', 'text' => 'Dentist and founder since 2001. Certified in dental hypnosis, focus on prevention and anxious patients.', 'file' => ['id' => $this->img( 'team-anna' ), 'type' => 'file']],
                ['title' => 'Dr. Jonas Weber', 'text' => 'Dentist and partner since 2014. Master of Science in implantology, focus on implants and root canals.', 'file' => ['id' => $this->img( 'team-jonas' ), 'type' => 'file']],
                ['title' => 'Dr. Mira Hofmann', 'text' => 'Dentist since 2019. Postgraduate training in paediatric dentistry, certified aligner provider.', 'file' => ['id' => $this->img( 'team-mira' ), 'type' => 'file']],
                ['title' => 'Felix Brandt', 'text' => 'Certified dental hygienist since 2016, professional cleaning, gum care and whitening.', 'file' => ['id' => $this->img( 'team-felix' ), 'type' => 'file']],
            ],
        ]];
    }


    /**
     * Creates a treatment page below the treatments page.
     *
     * @param Page $parent Treatments page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Hero headline
     * @param string $hero PHOTOS key of the hero image
     * @param string $image PHOTOS key of the text image
     * @param string $text Treatment description
     * @param array<int, array<string, string>> $items What is included
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function treatment( Page $parent, array $data, string $title, string $hero, string $image,
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
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'All treatments', 'url' => '/treatments'],
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
                'title' => 'What we offer',
                'cards' => $items,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from our patients',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let\'s talk about your teeth',
                'text' => 'Book an appointment online or call us. New patients usually get a first appointment within two weeks.',
                'buttons' => [
                    ['label' => 'Book an appointment', 'url' => '/appointment'],
                    ['label' => 'Call 0761 4567 230', 'url' => 'tel:+497614567230'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the treatments card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function treatments() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Our treatments',
            'columns' => '3',
            'cards' => [
                ['title' => 'Prevention and cleaning', 'text' => 'Check-ups and professional cleaning for healthy teeth and gums.', 'url' => '/prevention-cleaning', 'file' => ['id' => $this->img( 'checkup' ), 'type' => 'file']],
                ['title' => 'Fillings and root canals', 'text' => 'Tooth-coloured fillings and gentle treatments that save your teeth.', 'url' => '/fillings-root-canals', 'file' => ['id' => $this->img( 'dentist' ), 'type' => 'file']],
                ['title' => 'Crowns and bridges', 'text' => 'Ceramic crowns and bridges designed from a digital scan.', 'url' => '/crowns-bridges', 'file' => ['id' => $this->img( 'tools' ), 'type' => 'file']],
                ['title' => 'Implants', 'text' => 'Planned in 3D, with crowns that match your own teeth.', 'url' => '/implants', 'file' => ['id' => $this->img( 'implant' ), 'type' => 'file']],
                ['title' => 'Clear aligners', 'text' => 'Straighter teeth with nearly invisible trays.', 'url' => '/clear-aligners', 'file' => ['id' => $this->img( 'aligners' ), 'type' => 'file']],
                ['title' => 'Children\'s dentistry', 'text' => 'Relaxed visits that children look forward to.', 'url' => '/childrens-dentistry', 'file' => ['id' => $this->img( 'boy-chair' ), 'type' => 'file']],
                ['title' => 'Anxious patients', 'text' => 'Treatment at your pace, with a stop signal and optional sedation.', 'url' => '/anxious-patients', 'file' => ['id' => $this->img( 'consult' ), 'type' => 'file']],
                ['title' => 'Teeth whitening', 'text' => 'A brighter shade, safely and under dental supervision.', 'url' => '/teeth-whitening', 'file' => ['id' => $this->img( 'smile' ), 'type' => 'file']],
                ['title' => 'Dental emergencies', 'text' => 'Same-day appointments for toothache and accidents.', 'url' => '/dental-emergencies', 'file' => ['id' => $this->img( 'xray' ), 'type' => 'file']],
            ],
        ]];
    }
}
