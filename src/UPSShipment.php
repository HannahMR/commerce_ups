<?php

namespace Drupal\commerce_ups;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Ups\Entity\Package as UPSPackage;
use Ups\Entity\Address;
use Ups\Entity\ShipFrom;
use Ups\Entity\Shipment as APIShipment;
use Ups\Entity\Dimensions;

class UPSShipment extends UPSEntity {
  protected $shipment;
  protected $api_shipment;

  public function __construct(ShipmentInterface $shipment) {
    parent::__construct();
    $this->shipment = $shipment;
  }

  /**
   * @return \Ups\Entity\Shipment
   */
  public function getShipment() {
    $api_shipment = new APIShipment();
    $this->setShipTo($api_shipment);
    $this->setShipFrom($api_shipment);
    $this->setPackage($api_shipment);
    return $api_shipment;
  }

  /**
   * @param $api_shipment APIShipment.
   */
  public function setShipTo(APIShipment $api_shipment) {
    // todo: set all address fields
    $address = $this->shipment->getShippingProfile()->address;
    $to_address = new Address();
    $to_address->setAddressLine1($address->address_line1);
    $to_address->setAddressLine2($address->address_line2);
    $to_address->setCity($address->locality);
    $to_address->setStateProvinceCode($address->administrative_area);
    $to_address->setPostalCode($address->postal_code);
    $api_shipment->getShipTo()->setAddress($to_address);
  }

  /**
   * @param \Ups\Entity\Shipment $api_shipment
   */
  public function setShipFrom(APIShipment $api_shipment) {
    // todo: set all address fields.

    $address = $this->shipment->getOrder()->getStore()->getAddress();
    $from_address = new Address();
    $from_address->setAddressLine1($address->getAddressLine1());
    $from_address->setAddressLine2($address->getAddressLine2());
    $from_address->setCity($address->getDependentLocality());
    $from_address->setStateProvinceCode($address->getAdministrativeArea());
    $from_address->setPostalCode($address->getPostalCode());
    $ship_from = new ShipFrom();
    $ship_from->setAddress($from_address);
    $api_shipment->setShipFrom($ship_from);
  }

  /**
   * @param \Ups\Entity\Shipment $api_shipment
   */
  public function setPackage(APIShipment $api_shipment) {
    $package = new UPSPackage();

//    $this->calculateWeight($package);
//    $this->calculateDimensions($package);
    /*
     * @todo create setting to switch between these functions.
     */
    $this->setDimensions($package);
    $this->setWeight($package);
    $api_shipment->addPackage($package);
  }

  /**
   * @param \Ups\Entity\Package $ups_package
   */
  public function setDimensions(UPSPackage $ups_package) {
    $dimensions = new Dimensions();
    $dimensions->setHeight($this->shipment->getPackageType()->getHeight()->getNumber());
    $dimensions->setWidth($this->shipment->getPackageType()->getWidth()->getNumber());
    $dimensions->setLength($this->shipment->getPackageType()->getLength()->getNumber());
    $unit = $this->getUnitOfMeasure($this->shipment->getPackageType()->getLength()->getUnit());
    $dimensions->setUnitOfMeasurement($this->setUnitOfMeasurement($unit));
    $ups_package->setDimensions($dimensions);
  }

  /**
   * @param \Ups\Entity\Package $ups_package
   */
  public function calculateDimensions(UPSPackage $ups_package) {
    $dimensions = new Dimensions();
    $orderItems = $this->shipment->getOrder()->getItems();
    $itemLength = [];
    $itemHeight = [];
    $itemWidth = [];
    foreach ($orderItems as $item) {
      $item_dimensions = $item->getPurchasedEntity()->get('dimensions')->getValue();
      array_push($itemLength, $item_dimensions[0]['length']);
      array_push($itemHeight, $item_dimensions[0]['height']);
      array_push($itemWidth, $item_dimensions[0]['width']);
    }
    // Find the max dimensions for each measurements and use those.
    $dimensions->setHeight(intval(max($itemHeight)));
    $dimensions->setWidth(intval(max($itemWidth)));
    $dimensions->setLength(intval(max($itemLength)));
    $unit = $this->getUnitOfMeasure($this->shipment->getPackageType()->getLength()->getUnit());
    $dimensions->setUnitOfMeasurement($this->setUnitOfMeasurement($unit));
    $ups_package->setDimensions($dimensions);
  }

  /**
   * @param \Ups\Entity\Package $ups_package
   */
  public function setWeight(UPSPackage $ups_package) {
    $ups_package_weight = $ups_package->getPackageWeight();
    $ups_package_weight->setWeight($this->shipment->getPackageType()->getWeight()->getNumber());
    $unit = $this->getUnitOfMeasure($this->shipment->getPackageType()->getWeight()->getUnit());
    $ups_package_weight->setUnitOfMeasurement($this->setUnitOfMeasurement($unit));
  }

  /**
   * @param \Ups\Entity\Package $ups_package
   */
  public function calculateWeight(UPSPackage $ups_package) {
    $orderItems = $this->shipment->getOrder()->getItems();
    $itemWeight = [];
    $itemUnit = '';
    foreach ($orderItems as $item) {
      /** @var \Drupal\physical\Plugin\Field\FieldType\MeasurementItem $weight */
      $weight = $item->getPurchasedEntity()->get('weight')->first();
      $quantity = $item->getQuantity();
      $weightMeasure = $weight->toMeasurement()->multiply($quantity);
      $orderItemWeight = $weightMeasure->getNumber();
      array_push($itemWeight, $orderItemWeight);
      $itemUnit = $item->getPurchasedEntity()->get('weight')->unit;
    }
    $ups_package_weight = $ups_package->getPackageWeight();
    $ups_package_weight->setWeight(array_sum($itemWeight));

    /*
     * @todo physical also supports g & oz which should be accounted for here.
     */
    switch ($itemUnit) {
      case 'lb':
        $itemUnit = 'LBS';
        break;
      case 'kg':
        $itemUnit = 'KGS';
        break;
      default:
        $itemUnit = 'LBS';
        break;
    }
    $ups_package_weight->setUnitOfMeasurement($this->setUnitOfMeasurement($itemUnit));
  }

}
