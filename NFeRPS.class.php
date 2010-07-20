<?php

/**
 * Value Object class for RPS
 *
 * @package   NFePHPaulista
 * @author Reinaldo Nolasco Sanches <reinaldo@mandic.com.br>
 * @copyright Copyright (c) 2010, Reinaldo Nolasco Sanches
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */
class NFeRPS
{
  public $taxPayerRegisterProvider; // CCM do prestador
  public $serie;
  public $number;

  /* RPS ­ Recibo Provisório de Serviços
   * RPS-M ­ Recibo Provisório de Serviços proveniente de Nota Fiscal Conjugada (Mista)
   * RPS-C ­ Cupom */
  public $type = 'RPS';

  public $issueDate;

  /* N ­ Normal
   * C ­ Cancelada
   * E ­ Extraviada */
  public $status = 'N';

  /* T - Tributação no município de São Paulo
   * F - Tributação fora do município de São Paulo
   * I ­- Isento
   * J - ISS Suspenso por Decisão Judicial */
  public $taxation = 'I'; // I have problem with F and J options

  public $servicesValue = 0;
  public $deductionsValue = 0;

  public $serviceCode;
  public $servicesTaxRate; //Alíquota dos Serviços

  public $withheldTax = false; // ISS retido

  public $contractorRPS; // new ContractorRPS

  public $breakdown;
}

/**
 * Value Object class for Contractor
 *
 * @author Reinaldo Nolasco Sanches <reinaldo@mandic.com>
 */
class ContractorRPS
{
  public $federalTaxNumber; // CPF/CNPJ
  public $taxPayerRegister; // CCM

  public $type = 'C'; // C = Corporate (CNPJ), F = Personal (CPF)

  public $name;

  public $addressType;
  public $address;
  public $addressNumber;
  public $complement;
  public $district;
  public $city;
  public $state;
  public $zip;

  public $email;
  public $email2;
}