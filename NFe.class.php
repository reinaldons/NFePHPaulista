<?php

/**
 * Creates XMLs and Webservices communication
 *
 * Original names of Brazil specific abbreviations have been kept:
 * - CNPJ = Federal Tax Number
 * - CPF = Personal/Individual Taxpayer Registration Number
 * - CCM = Taxpayer Register (for service providers who pay ISS for local town/city hall)
 * - ISS = Service Tax
 *
 * @package   NFePHPaulista
 * @author    Reinaldo Nolasco Sanches <reinaldo@mandic.com.br>
 * @copyright Copyright (c) 2010, Reinaldo Nolasco Sanches
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
class NFe
{
  private $providerFederalTaxNumber = ''; // Your CNPJ

  private $providerTaxpayerRegister = ''; // Your CCM

  private $passphrase = ''; // Cert passphrase

  private $pkcs12  = '/patch/for/nfe/certificates/nfe.pfx';

  private $certDir = '/patch/for/nfe/certificates'; // Dir for .pem certs

  private $privateKey;

  private $publicKey;

  private $X509Certificate;

  private $key;

  private $connectionSoap;

  private $urlXsi = 'http://www.w3.org/2001/XMLSchema-instance';

  private $urlXsd = 'http://www.w3.org/2001/XMLSchema';

  private $urlNfe = 'http://www.prefeitura.sp.gov.br/nfe';

  private $urlDsig = 'http://www.w3.org/2000/09/xmldsig#';

  private $urlCanonMeth = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

  private $urlSigMeth = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

  private $urlTransfMeth_1 = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

  private $urlTransfMeth_2 = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

  private $urlDigestMeth = 'http://www.w3.org/2000/09/xmldsig#sha1';



  public function __construct()
  {
    $this->privateKey = $this->certDir . '/privatekey.pem';
    $this->publicKey = $this->certDir . '/publickey.pem';
    $this->key = $this->certDir . '/key.pem';

    if ( $this->loadCert() ) {
      error_log( __METHOD__ . ': Certificate is OK!' );
    } else {
      error_log( __METHOD__ . ': Certificate is not OK!' );
    }
  }



  private function validateCert( $cert )
  {
    $data = openssl_x509_read( $cert );
    $certData = openssl_x509_parse( $data );

    $certValidDate = gmmktime( 0, 0, 0, substr( $certData['validTo'], 2, 2 ), substr( $certData['validTo'], 4, 2 ), substr( $certData['validTo'], 0, 2 ) );

    if ( $certValidDate < time() ){
      error_log( __METHOD__ . ': Certificate expired in ' . date( 'Y-m-d', $certValidDate ) );
      return false;
    }

    return true;
  }



  private function loadCert()
  {
    $x509CertData = array();

    if ( ! openssl_pkcs12_read( file_get_contents( $this->pkcs12 ), $x509CertData, $this->passphrase ) ) {
      error_log( __METHOD__ . ': Certificate cannot be read. File is corrupted or invalid format.' );

      return false;
    }

    $this->X509Certificate = preg_replace( "/[\n]/", '', preg_replace( '/\-\-\-\-\-[A-Z]+ CERTIFICATE\-\-\-\-\-/', '', $x509CertData['cert'] ) );

    if ( ! self::validateCert( $x509CertData['cert'] ) ) {
      return false;
    }

    if ( ! is_dir( $this->certDir ) ) {
      if ( ! mkdir( $this->certDir, 0777 ) ) {
        error_log( __METHOD__ . ': Cannot create folder ' . $this->certDir );
        return false;
      }
    }

    if ( ! file_exists( $this->privateKey ) ) {
      if ( ! file_put_contents( $this->privateKey, $x509CertData['pkey'] ) ) {
        error_log( __METHOD__ . ': Cannot create file ' . $this->privateKey );
        return false;
      }
    }

    if ( ! file_exists( $this->publicKey ) ) {
      if ( ! file_put_contents( $this->publicKey, $x509CertData['cert'] ) ) {
        error_log( __METHOD__ . ': Cannot create file ' . $this->publicKey );
        return false;
      }
    }

    if ( ! file_exists( $this->key ) ) {
      if ( ! file_put_contents( $this->key, $x509CertData['cert'] . $x509CertData['pkey'] ) ) {
        error_log( __METHOD__ . ': Cannot create file ' . $this->key );
        return false;
      }
    }

    return true;
  }



  public function start()
  {
    $wsdl = 'https://nfe.prefeitura.sp.gov.br/ws/lotenfe.asmx?WSDL';
    $params = array(
      'local_cert' => $this->key,
      'passphrase' => $this->passphrase,
      'trace' => 1,
      'connection_timeout' => 300,
      'encoding' => 'utf-8',
      'cache_wsdl' => WSDL_CACHE_NONE
    );

    $this->connectionSoap = new SoapClient( $wsdl, $params );
  }



  private function send( $operation, $xmlDoc )
  {
    self::start();

    $this->signXML( $xmlDoc );

    $params = array(
      'VersaoSchema' => 1,
      'MensagemXML' => $xmlDoc->saveXML()
    );

    try {
      $result = $this->connectionSoap->$operation( $params );
    } catch( SoapFault $e ) {
      error_log( 'Exception: ' . $e->getMessage() );
      return false;
    }

    return new SimpleXMLElement( $result->RetornoXML );
  }



  private function createXML( $operation )
  {
    $xmlDoc = new DOMDocument( '1.0', 'UTF-8' );
    $xmlDoc->preservWhiteSpace = false;
    $xmlDoc->formatOutput = false;

    $data = '<?xml version="1.0" encoding="UTF-8"?><Pedido' . $operation . ' xmlns:xsd="' . $this->urlXsd .'" xmlns="' . $this->urlNfe . '" xmlns:xsi="' . $this->urlXsi . '"></Pedido' . $operation . '>';

    $xmlDoc->loadXML( str_replace( array("\r\n", "\n", "\r"), '', $data ), LIBXML_NOBLANKS | LIBXML_NOEMPTYTAG );

    $root = $xmlDoc->documentElement;

    $header = $xmlDoc->createElementNS( '', 'Cabecalho' );
    $root->appendChild( $header );
    $header->setAttribute( 'Versao', 1 );
    $cnpjSender = $xmlDoc->createElement( 'CPFCNPJRemetente' );
    $cnpjSender->appendChild( $xmlDoc->createElement( 'CNPJ', $this->providerFederalTaxNumber ) );
    $header->appendChild( $cnpjSender );

    return $xmlDoc;
  }



  private function signXML( &$xmlDoc )
  {
    $root = $xmlDoc->documentElement;

    // DigestValue is a base64 sha1 hash with root tag content without Signature tag
    $digestValue = base64_encode( hash( 'sha1', $root->C14N( false, false, null, null ), true ) );

    $signature = $xmlDoc->createElementNS( $this->urlDsig, 'Signature' );
    $root->appendChild( $signature );

    $signedInfo = $xmlDoc->createElement( 'SignedInfo' );
    $signature->appendChild( $signedInfo );
    $newNode = $xmlDoc->createElement( 'CanonicalizationMethod' );
    $signedInfo->appendChild( $newNode );
    $newNode->setAttribute( 'Algorithm', $this->urlCanonMeth );
    $newNode = $xmlDoc->createElement( 'SignatureMethod' );
    $signedInfo->appendChild( $newNode );
    $newNode->setAttribute( 'Algorithm', $this->urlSigMeth );
    $reference = $xmlDoc->createElement( 'Reference' );
    $signedInfo->appendChild( $reference );
    $reference->setAttribute( 'URI', '' );
    $transforms = $xmlDoc->createElement( 'Transforms' );
    $reference->appendChild( $transforms );
    $newNode = $xmlDoc->createElement( 'Transform' );
    $transforms->appendChild( $newNode );
    $newNode->setAttribute( 'Algorithm', $this->urlTransfMeth_1 );
    $newNode = $xmlDoc->createElement( 'Transform' );
    $transforms->appendChild( $newNode );
    $newNode->setAttribute( 'Algorithm', $this->urlTransfMeth_2 );
    $newNode = $xmlDoc->createElement( 'DigestMethod' );
    $reference->appendChild( $newNode );
    $newNode->setAttribute( 'Algorithm', $this->urlDigestMeth );
    $newNode = $xmlDoc->createElement( 'DigestValue', $digestValue );
    $reference->appendChild( $newNode );

    // SignedInfo Canonicalization (Canonical XML)
    $signedInfoC14n = $signedInfo->C14N( false, false, null, null );

    // SignatureValue is a base64 SignedInfo tag content
    $signatureValue = '';
    $pkeyId = openssl_get_privatekey( file_get_contents( $this->privateKey ) );
    openssl_sign( $signedInfoC14n, $signatureValue, $pkeyId );
    $newNode = $xmlDoc->createElement( 'SignatureValue', base64_encode( $signatureValue ) );
    $signature->appendChild( $newNode );
    $keyInfo = $xmlDoc->createElement('KeyInfo');
    $signature->appendChild($keyInfo);
    $x509Data = $xmlDoc->createElement( 'X509Data' );
    $keyInfo->appendChild( $x509Data );
    $newNode = $xmlDoc->createElement( 'X509Certificate', $this->X509Certificate );
    $x509Data->appendChild( $newNode );

    openssl_free_key( $pkeyId );
  }



  private function signRPS( NFeRPS $rps, &$rpsNode )
  {
    $content = sprintf( '%08s', $rps->taxPayerRegisterProvider ) .
               sprintf('%-5s',$rps->serie ) . // 5 chars
               sprintf( '%012s', $rps->number ) .
               date( 'Ymd', $rps->issueDate ) .
               $rps->taxation .
               $rps->status .
               ( ( $rps->withheldTax ) ? 'S' : 'N' ) .
               sprintf( '%015s', str_replace( array( '.', ',' ),'', number_format( $rps->servicesValue, 2 ) ) ).
               sprintf( '%015s', str_replace( array( '.', ',' ), '', number_format( $rps->deductionsValue, 2 ) ) ) .
               sprintf( '%05s', $rps->serviceCode ) .
               ( ( $rps->contractorRPS->type == 'F' ) ? '1' : '2' ) .
               sprintf( '%014s', $rps->contractorRPS->federalTaxNumber );

    $signatureValue = '';
    $pkeyId = openssl_get_privatekey( file_get_contents( $this->privateKey ) );
    openssl_sign( $content, $signatureValue, $pkeyId, OPENSSL_ALGO_SHA1 );
    openssl_free_key( $pkeyId );

    $rpsNode->appendChild( new DOMElement( 'Assinatura', base64_encode( $signatureValue ) ) );
  }



  private function insertRPS( NFeRPS $rps, &$xmlDoc )
  {
    $rpsNode = $xmlDoc->createElementNS( '', 'RPS' );
    $xmlDoc->documentElement->appendChild( $rpsNode );

    $this->signRPS( $rps, $rpsNode );

    $rpsKey = $xmlDoc->createElement( 'ChaveRPS' ); // 1-1
    $rpsKey->appendChild( $xmlDoc->createElement( 'InscricaoPrestador', $rps->taxPayerRegisterProvider ) ); // 1-1
    $rpsKey->appendChild( $xmlDoc->createElement( 'SerieRPS', $rps->serie ) ); // 1-1 DHC AAAAA / alog AAAAB
    $rpsKey->appendChild( $xmlDoc->createElement( 'NumeroRPS', $rps->number ) ); // 1-1
    $rpsNode->appendChild( $rpsKey );

    /* RPS ­ Recibo Provisório de Serviços
     * RPS-M ­ Recibo Provisório de Serviços proveniente de Nota Fiscal Conjugada (Mista)
     * RPS-C ­ Cupom */
    $rpsNode->appendChild( $xmlDoc->createElement( 'TipoRPS', $rps->type ) ); // 1-1

    $rpsNode->appendChild( $xmlDoc->createElement( 'DataEmissao', date( 'Y-m-d', $rps->issueDate ) ) ); // 1-1

    /* N ­ Normal
     * C ­ Cancelada
     * E ­ Extraviada */
    $rpsNode->appendChild( $xmlDoc->createElement( 'StatusRPS', $rps->status ) ); // 1-1

    /* T - Tributação no município de São Paulo
     * F - Tributação fora do município de São Paulo
     * I ­- Isento
     * J - ISS Suspenso por Decisão Judicial */
    $rpsNode->appendChild( $xmlDoc->createElement( 'TributacaoRPS', $rps->taxation ) ); // 1-1

    $rpsNode->appendChild( $xmlDoc->createElement( 'ValorServicos', sprintf( "%s", $rps->servicesValue ) ) ); // 1-1
    $rpsNode->appendChild( $xmlDoc->createElement( 'ValorDeducoes', sprintf( "%s", $rps->deductionsValue ) ) ); // 1-1

    $rpsNode->appendChild( $xmlDoc->createElement( 'CodigoServico', $rps->serviceCode ) ); // 1-1
    $rpsNode->appendChild( $xmlDoc->createElement( 'AliquotaServicos', $rps->servicesTaxRate ) ); // 1-1

    $rpsNode->appendChild( $xmlDoc->createElement( 'ISSRetido', ( ( $rps->withheldTax ) ? 'true' : 'false' ) ) ); // 1-1

    $cnpj = $xmlDoc->createElement( 'CPFCNPJTomador' ); // 0-1
    $cnpj->appendChild( $xmlDoc->createElement( 'CNPJ', sprintf( '%014s', $rps->contractorRPS->federalTaxNumber ) ) );
    $rpsNode->appendChild( $cnpj );

    $rpsNode->appendChild( $xmlDoc->createElement( 'RazaoSocialTomador', $rps->contractorRPS->name ) ); // 0-1
/*
    $address = $xmlDoc->createElement( 'EnderecoTomador' ); // 0-1
    $address->appendChild( $xmlDoc->createElement( 'TipoLogradouro', $rps->contractorRPS->addressType ) );
    $address->appendChild( $xmlDoc->createElement( 'Logradouro', $rps->contractorRPS->address ) );
    $address->appendChild( $xmlDoc->createElement( 'NumeroEndereco', $rps->contractorRPS->addressNumber ) );
    $address->appendChild( $xmlDoc->createElement( 'ComplementoEndereco', $rps->contractorRPS->complement ) );
    $address->appendChild( $xmlDoc->createElement( 'Bairro', $rps->contractorRPS->district ) );
    $address->appendChild( $xmlDoc->createElement( 'Cidade', $rps->contractorRPS->city ) );
    $address->appendChild( $xmlDoc->createElement( 'UF', $rps->contractorRPS->state ) );
    $address->appendChild( $xmlDoc->createElement( 'CEP', $rps->contractorRPS->zip ) );
    $rpsNode->appendChild( $address );
*/
    $rpsNode->appendChild( $xmlDoc->createElement( 'EmailTomador', $rps->contractorRPS->email ) ); // 0-1

    $rpsNode->appendChild( $xmlDoc->createElement( 'Discriminacao', $rps->breakdown ) ); // 1-1
  }



  /**
   * Send a RPS to replace for NF-e
   *
   * @param NFeRPS $rps
   */
  public function sendRPS( NFeRPS $rps )
  {
    $operation = 'EnvioRPS';

    $xmlDoc = $this->createXML( $operation );

    $this->insertRPS( $rps, $xmlDoc );

    $returnXmlDoc = $this->send( $operation, $xmlDoc );

    return $returnXmlDoc;
  }



  /**
   * Send a batch of RPSs to replace for NF-e
   *
   * @param array $rangeDate ( 'start' => start date of RPSs, 'end' => end date of RPSs )
   * @param array $totalValue ( 'servives' => total value of RPSs, 'deductions' => total deductions on values of RPSs )
   * @param array $rps Collection of NFeRPS
   */
  public function sendRPSBatch( $rangeDate, $totalValue, $rps )
  {
    $operation = 'EnvioLoteRPS';

    $xmlDoc = $this->createXML( $operation );

    $header = $xmlDoc->documentElement->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $header->appendChild( $xmlDoc->createElement( 'transacao', 'false' ) );
    $header->appendChild( $xmlDoc->createElement( 'dtInicio', $rangeDate['start'] ) );
    $header->appendChild( $xmlDoc->createElement( 'dtFim', $rangeDate['end'] ) );
    $header->appendChild( $xmlDoc->createElement( 'QtdRPS', count( $rps ) ) );
    $header->appendChild( $xmlDoc->createElement( 'ValorTotalServicos', $totalValue['servives'] ) );
    $header->appendChild( $xmlDoc->createElement( 'ValorTotalDeducoes', $totalValue['deductions'] ) );

    foreach ( $rps as $item ) {
      $this->insertRPS( $item, $xmlDoc );
    }

    return $this->send( $operation, $xmlDoc );
  }



  /**
   * Send a batch of RPSs to replace for NF-e for test only
   *
   * @param array $rangeDate ( 'start' => start date of RPSs, 'end' => end date of RPSs )
   * @param array $totalValue ( 'servives' => total value of RPSs, 'deductions' => total deductions on values of RPSs )
   * @param array $rps Collection of NFeRPS
   */
  public function sendRPSBatchTest( $rangeDate, $totalValue, $rps )
  {
    $operation = 'EnvioLoteRPS';

    $xmlDoc = $this->createXML( $operation );

    $header = $xmlDoc->documentElement->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $header->appendChild( $xmlDoc->createElement( 'transacao', 'false' ) );
    $header->appendChild( $xmlDoc->createElement( 'dtInicio', $rangeDate['start'] ) );
    $header->appendChild( $xmlDoc->createElement( 'dtFim', $rangeDate['end'] ) );
    $header->appendChild( $xmlDoc->createElement( 'QtdRPS', count( $rps ) ) );
    $header->appendChild( $xmlDoc->createElement( 'ValorTotalServicos', $totalValue['servives'] ) );
    $header->appendChild( $xmlDoc->createElement( 'ValorTotalDeducoes', $totalValue['deductions'] ) );

    foreach ( $rps as $item ) {
      $this->insertRPS( $item, $xmlDoc );
    }

    $return = $this->send( 'TesteEnvioLoteRPS', $xmlDoc );
    $xmlDoc->formatOutput = true;
    error_log( __METHOD__ . ': ' . $xmlDoc->saveXML() );

    return $return;
  }



  /**
   *
   * @param array $nfe Array of NFe numbers
   */
  public function cancelNFe( $nfeNumbers )
  {
    $operation = 'CancelamentoNFe';

    $xmlDoc = $this->createXML( $operation );

    $root = $xmlDoc->documentElement;
    $header = $root->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $header->appendChild( $xmlDoc->createElement( 'transacao', 'false' ) );

    foreach ( $nfeNumbers as $nfeNumber ) {
      $detail = $xmlDoc->createElement( 'Detalhe' );
      $root->appendChild( $detail );

      $nfeKey = $xmlDoc->createElement( 'ChaveNFe' ); // 1-1
      $nfeKey->appendChild( $xmlDoc->createElement( 'InscricaoPrestador', $this->providerTaxpayerRegister ) ); // 1-1
      $nfeKey->appendChild( $xmlDoc->createElement( 'NumeroNFe', $nfeNumber ) ); // 1-1

      $content = sprintf( '%08s', $this->providerTaxpayerRegister) .
                 sprintf( '%012s', $nfeNumber );
      $signatureValue = '';
      $digestValue = base64_encode( hash( 'sha1', $content, true ) );
      $pkeyId = openssl_get_privatekey( file_get_contents( $this->privateKey ) );
      openssl_sign( $digestValue, $signatureValue, $pkeyId );
      openssl_free_key( $pkeyId );

      $nfeKey->appendChild( new DOMElement( 'AssinaturaCancelamento', base64_encode( $signatureValue ) ) );
      $detail->appendChild( $nfeKey );
    }

    return $this->send( $operation, $xmlDoc );
  }



  public function queryNFe( $nfeNumber, $rpsNumber, $rpsSerie )
  {
    $operation = 'ConsultaNFe';

    $xmlDoc = $this->createXML( $operation );

    $root = $xmlDoc->documentElement;

    $detailNfe = $xmlDoc->createElement( 'Detalhe' );
    $root->appendChild( $detailNfe );

    $nfeKey = $xmlDoc->createElement( 'ChaveNFe' ); // 1-1
    $nfeKey->appendChild( $xmlDoc->createElement( 'InscricaoPrestador', $this->providerTaxpayerRegister ) ); // 1-1
    $nfeKey->appendChild( $xmlDoc->createElement( 'NumeroNFe', $nfeNumber) ); // 1-1
    $detailNfe->appendChild( $nfeKey );

    $detailRps = $xmlDoc->createElement( 'Detalhe' );
    $root->appendChild( $detailRps );

    $rpsKey = $xmlDoc->createElement( 'ChaveRPS' ); // 1-1
    $rpsKey->appendChild( $xmlDoc->createElement( 'InscricaoPrestador', $this->providerTaxpayerRegister ) ); // 1-1
    $rpsKey->appendChild( $xmlDoc->createElement( 'SerieRPS', $rpsSerie ) ); // 1-1 DHC AAAAA / alog AAAAB
    $rpsKey->appendChild( $xmlDoc->createElement( 'NumeroRPS', $rpsNumber ) ); // 1-1
    $detailRps->appendChild( $rpsKey );

    return $this->send( $operation, $xmlDoc );
  }


  /**
   * queryNFeReceived and queryNFeIssued have the same XML request model
   *
   * @param string $cnpj
   * @param string $ccm
   * @param string $startDate YYYY-MM-DD
   * @param string $endDate YYYY-MM-DD
   */
  private function queryNFeWithDateRange( $cnpj, $ccm, $startDate, $endDate )
  {
    $operation = 'ConsultaNFePeriodo';

    $xmlDoc = $this->createXML( $operation );

    $header = $xmlDoc->documentElement->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $cnpjTaxpayer = $xmlDoc->createElement( 'CPFCNPJ' );
    $cnpjTaxpayer->appendChild( $xmlDoc->createElement( 'CNPJ', $cnpj ) );
    $header->appendChild( $cnpjTaxpayer );

    $ccmTaxpayer = $xmlDoc->createElement( 'Inscricao', $ccm );
    $header->appendChild( $ccmTaxpayer );

    $startDateNode = $xmlDoc->createElement( 'dtInicio', $startDate );
    $header->appendChild( $startDateNode );

    $endDateNode = $xmlDoc->createElement( 'dtFim', $endDate );
    $header->appendChild( $endDateNode );

    $pageNumber = $xmlDoc->createElement( 'NumeroPagina', 1 );
    $header->appendChild( $pageNumber );

    return $xmlDoc;
  }


  /**
   * Query NF-e's that CNPJ/CCM company received from other companies
   *
   * @param string $cnpj
   * @param string $ccm
   * @param string $startDate YYYY-MM-DD
   * @param string $endDate YYYY-MM-DD
   */
  public function queryNFeReceived( $cnpj, $ccm, $startDate, $endDate )
  {
    $operation = 'ConsultaNFeRecebidas';

    $xmlDoc = $this->queryNFeWithDateRange( $cnpj, $ccm, $startDate, $endDate );

    return $this->send( $operation, $xmlDoc );
  }


  /**
   * Query NF-e's that CNPJ/CCM company issued to other companies
   *
   * @param string $cnpj
   * @param string $ccm
   * @param string $startDate YYYY-MM-DD
   * @param string $endDate YYYY-MM-DD
   */
  public function queryNFeIssued( $cnpj, $ccm, $startDate, $endDate )
  {
    $operation = 'ConsultaNFeEmitidas';

    $xmlDoc = $this->queryNFeWithDateRange( $cnpj, $ccm, $startDate, $endDate );

    return $this->send( $operation, $xmlDoc );
  }



  public function queryBatch( $batchNumber )
  {
    $operation = 'ConsultaLote';

    $xmlDoc = $this->createXML( $operation );

    $header = $xmlDoc->documentElement->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $header->appendChild( $xmlDoc->createElement( 'NumeroLote', $batchNumber ) );

    return $this->send( $operation, $xmlDoc );
  }


  /**
   * If $batchNumber param is null, last match info will be returned
   *
   * @param integer $batchNumber
   */
  public function queryBatchInfo( $batchNumber = null )
  {
    $operation = 'InformacoesLote';

    $xmlDoc = $this->createXML( $operation );

    $header = $xmlDoc->documentElement->getElementsByTagName( 'Cabecalho' )->item( 0 );

    $header->appendChild( $xmlDoc->createElement( 'InscricaoPrestador', $this->providerTaxpayerRegister ) );

    if ( $batchNumber ) {
      $header->appendChild( $xmlDoc->createElement( 'NumeroLote', $batchNumber ) );
    }

    return $this->send( $operation, $xmlDoc );
  }


  /**
   * Returns CCM for given CNPJ
   *
   * @param string $cnpj
   */
  public function queryCNPJ( $cnpj )
  {
    $operation = 'ConsultaCNPJ';

    $xmlDoc = $this->createXML( $operation );

    $root = $xmlDoc->documentElement;

    $cnpjTaxpayer = $xmlDoc->createElement( 'CNPJContribuinte' );
    $cnpjTaxpayer->appendChild( $xmlDoc->createElement( 'CNPJ', (string) sprintf( '%014s', $cnpj ) ) );
    $root->appendChild( $cnpjTaxpayer );

    $return = $this->send( $operation, $xmlDoc );

    return $return->Detalhe->InscricaoMunicipal;
  }



  /**
   * Create a line with RPS description for batch file
   *
   * @param unknown_type $rps
   * @param unknown_type $body
   */
  private function insertTextRPS( NFeRPS $rps, &$body )
  {
    if ( $rps->servicesValue > 0 ) {
      $line = "2" .
              sprintf( "%-5s", $rps->type ) .
              sprintf( "%-5s", $rps->serie ) .
              sprintf( '%012s', $rps->number ) .
              date( 'Ymd', $rps->issueDate ) .
              $rps->taxation .
              sprintf( '%015s', str_replace( '.', '', sprintf( '%.2f', $rps->servicesValue ) ) ) .
              sprintf( '%015s', str_replace( '.', '', sprintf( '%.2f', $rps->deductionsValue ) ) ) .
              sprintf( '%05s', $rps->serviceCode ) .
              sprintf( '%04s', str_replace( '.', '', $rps->servicesTaxRate ) ) .
              ( ( $rps->withheldTax ) ? '1' : '2' ) .
              ( ( $rps->contractorRPS->type == 'F' ) ? '1' : '2' ) .
              sprintf( '%014s', $rps->contractorRPS->federalTaxNumber ) .
              sprintf( '%08s', $rps->contractorRPS->taxPayerRegister ) .
              sprintf( '%012s', '' ) .
              sprintf( '%-75s', mb_convert_encoding( $rps->contractorRPS->name, 'ISO-8859-1', 'UTF-8' ) ) .
              sprintf( '%3s', ( ( $rps->contractorRPS->addressType == 'R' ) ? 'Rua' : '' ) ) .
              sprintf( '%-50s', mb_convert_encoding( $rps->contractorRPS->address, 'ISO-8859-1', 'UTF-8' ) ) .
              sprintf( '%-10s', $rps->contractorRPS->addressNumber ) .
              sprintf( '%-30s', mb_convert_encoding( $rps->contractorRPS->complement, 'ISO-8859-1', 'UTF-8' ) ) .
              sprintf( '%-30s', mb_convert_encoding( $rps->contractorRPS->district, 'ISO-8859-1', 'UTF-8' ) ) .
              sprintf( '%-50s', mb_convert_encoding( $rps->contractorRPS->city, 'ISO-8859-1', 'UTF-8' ) ) .
              sprintf( '%-2s', $rps->contractorRPS->state ) .
              sprintf( '%08s', $rps->contractorRPS->zip ) .
              sprintf( '%-75s', $rps->contractorRPS->email ) .
              str_replace( "\n", '|', mb_convert_encoding( $rps->breakdown, 'ISO-8859-1', 'UTF-8' ) );

      $body .= $line . chr( 13 ) . chr( 10 );
    }
  }



  /**
   * Create a batch file with NF-e text layout
   *
   * @param unknown_type $rangeDate
   * @param unknown_type $totalValue
   * @param unknown_type $rps
   */
  public function textFile( $rangeDate, $totalValue, $rps )
  {
    $file = '';

    $header = "1" .
              "001" .
              $this->providerTaxpayerRegister .
              date( "Ymd", $rangeDate['start'] ) .
              date( "Ymd", $rangeDate['end'] ) .
              chr( 13 ) . chr( 10 );

    $body = '';
    foreach ( $rps as $item ) {
      $this->insertTextRPS( $item, $body );
    }

    $footer = "9" .
              sprintf( "%07s", count( $rps ) ) .
              sprintf( "%015s", str_replace( '.', '', sprintf( '%.2f', $totalValue['servives'] ) ) ) .
              sprintf( "%015s", str_replace( '.', '', sprintf( '%.2f', $totalValue['deductions'] ) ) ) .
              chr( 13 ) . chr( 10 );

    $rpsDir = '/patch/for/rps/batch/file';
    $rpsFileName = date( "Y-m-d_Hi" ) . '.txt';
    $rpsFullPath = $rpsDir . '/' . $rpsFileName;
    if ( ! is_dir( $rpsDir ) ) {
      if ( ! mkdir( $rpsDir, 0777 ) ) {

      }
    }

    if ( ! file_put_contents( $rpsFullPath, $header . $body . $footer ) ) {
      error_log( __METHOD__ . ': Cannot create rps file ' . $rpsFullPath );
      return false;
    }

    return $rpsFullPath;
  }
}