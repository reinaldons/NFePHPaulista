
> **ATTENTION!**
>
> I don't work with PHP since 2012 and have no more contact with the system that I created this classes for since 2011.
> Please, if you have improvements for this project or updates, fork this project and help others with your updates :D
> I can't accept pull request because I have no way to test.

> Thanks!

---

## Simple doc for NFePHPaulista

NFePHPaulista are two classes for NF-e Paulista Webservices communication. Those classes are both in MIT License. 

FAQ NFe Paulista: http://www.prefeitura.sp.gov.br/portal/nfe/perguntas_mais_frequentes/index.php?content=faq
Doc Webservices: http://ww2.prefeitura.sp.gov.br/nfe/files/NFe-Web-Service-v2-2.pdf
IBGE City Codes: http://www.ibge.gov.br/concla/cod_area/cod_area.php
                 http://www.ibge.gov.br/concla/cod_area/tabela_municipios.xls

---

#### NFe class make Webservices communication. You need to set values of come variables

```php
$providerFederalTaxNumber = ''; // Your CNPJ
$providerTaxpayerRegister = ''; // Your CCM
$passphrase = ''; // Cert passphrase
$pkcs12  = '/patch/for/nfe/certificates/nfe.pfx';
$certDir = '/patch/for/nfe/certificates'; // Dir for .pem certs
```
NFeRPS class is a Value Object. You can make a __construct or populate like a stdClass

---

#### Simple example for NFeRPS Array population

```php
  $rpsArray = array();
  $totalServicesValue = 0;
  foreach ( $yourInfoArray as $invoice ) {
    $nfeRPS = new NFeRPS();
    /* ContractorRPS is aggregate on NFeRPS */
    /* $nfeRPS->$contractorRPS = new ContractorRPS(); */
    
    /* Your logic for $nfeRPS population. You can make a __construct for NFeRPS */
  
    $rpsArray[] = $nfeRPS;
    $totalServicesValue += $invoice->getValor();
  }
```

---

#### Webservices communication

```php
  $nfe = new NFe();

  /* Use sendRPSBatchTest for tests sendRPSBatch for production */
  $result = $nfe->sendRPSBatchTest( array( 'start' => date( 'Y-m-d' ), 'end' => date( 'Y-m-d' ) ),
                                    array( 'servives' => $totalServicesValue, 'deductions' => 0 ),
                                    $rpsArray );
```

You can use 'textFile' method to create batch file for site upload instead webservice communication:

```php
  $result = $nfe->textFile( array( 'start' => date( 'U' ), 'end' => date( 'U' ) ),
                            array( 'servives' => $totalServicesValue, 'deductions' => 0 ),
                            $rpsArray );
```

---

#### Webservices and NFe class methods

| Webservice method                                                                      | NFe class method   |
|----------------------------------------------------------------------------------|--------------------------|
| EnvioRPSResponse EnvioRPS()                                                  | sendRPS                 |
| EnvioLoteRPSResponse EnvioLoteRPS()                                    | sendRPSBatch        |
| TesteEnvioLoteRPSResponse TesteEnvioLoteRPS()                  | sendRPSBatchTest |
| CancelamentoNFeResponse CancelamentoNFe()                       | cancelNFe               |
| ConsultaNFeResponse ConsultaNFe()                                        | queryNFe                 |
| ConsultaNFeRecebidasResponse ConsultaNFeRecebidas()       | queryNFeReceived |
| ConsultaNFeEmitidasResponse ConsultaNFeEmitidas()              | queryNFeIssued      |
| ConsultaLoteResponse ConsultaLote()                                        | queryBatch             |
| ConsultaInformacoesLoteResponse ConsultaInformacoesLote() | queryBatchInfo        |
| ConsultaCNPJResponse ConsultaCNPJ()                                     | queryCNPJ              |
