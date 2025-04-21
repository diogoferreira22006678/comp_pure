<?php

// components/com_pure/helpers/config.php

$base_url_site = 'http://localhost:8013/';
$base_api ='https://research.ulusofona.pt/ws/api/';


// 37e0cf22-558c-4af8-99b7-717641a3f596 - Universidade Lusófona
// a0541f4f-b536-4945-ae33-f25c288ef535 - Centro Universitário Lusófona - Porto
// 52e5cac8-dd34-45f5-b474-584901262e80 - Centro Universitário Lusófona - Lisboa
// af6df28c-a6c3-4cb0-a000-6285d6f02352 - Escola de Psicologia e Ciências da Vida
// dd553115-3ba3-4016-a0ee-af7c72f278cf - Escola de Ciências e Tecnologias Saúde
// 4838c312-93ef-44b3-b3f0-d00bf4bc7030 - COPELABS (FCT) - Centro de Investigação em Computação Centrada nas Pessoas e Cognição (CTS)
// 79a7d58b-ba26-401a-8fb7-d5b5606228b8 - CBIOS (FCT) - Centro de Investigação em Biociências e Tecnologias da Saúde
// cfe30a38-7136-467c-a237-920413a83331 - Escola de Comunicação, Arquitetura, Artes e Tecnologias da Informação
// 5e65fcc2-d568-440d-acec-703351ccf1bf - CICANT (FCT) - Centro de Investigação em Comunicação Aplicada, Cultura e Novas Tecnologias
// 6fe7327f-dfcf-4d2d-b760-4673655533d6 - HEI-LAB (FCT) - Digital Laboratories for Environments and Human Interactions

$institution = '5e65fcc2-d568-440d-acec-703351ccf1bf'; // CICANT (FCT)
$institutionName = 'CICANT (FCT)';


return [ 
    'base_url' => $base_url_site,
    'base_api' => $base_api,
    'institution' => $institution,
    'institution_name' => $institutionName,
];