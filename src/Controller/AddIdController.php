<?php

declare(strict_types=1);

namespace Drupal\killam_rentcafe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This controller populates the newly minted field_rentcafe_property_code.
 */
final class AddIdController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    protected $entityTypeManager,

  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $mappings = $this->getMapping();
    foreach ($mappings as $propertyCode => $buildingCode) {
      $nids = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->condition('field_building_code', $buildingCode)
        ->condition('type', 'property')
        ->accessCheck(FALSE)
        ->execute();

      if (!empty($nids)) {
        $nid = reset($nids);
        $property = $this->entityTypeManager->getStorage('node')->load($nid);
        if ($property) {
          $property->set('field_rentcafe_property_code', $propertyCode);
          $property->save();
        }
      }
    }

    return [
      'content' => [
        '#type' => 'item',
        '#markup' => $this->t('Successfully added Rentcafe Property IDs.'),
      ],
    ];
  }

  /**
   * Provides a mapping of property codes to building codes.
   *
   * @return array
   *   An associative array of property codes to building codes.
   */
  public function getMapping() {
    return [
      "p1817724" => "victoria",
      "p1817725" => "killick",
      "p1817722" => "lf",
      "p0510932" => "woodward",
      "p0510266" => "wh",
      "p0510295" => "120mck",
      "p0510293" => "25mck",
      "p0510294" => "110mck",
      "p0510296" => "200rey",
      "p0589344" => "k4",
      "p0565861" => "K3",
      "p0519220" => "k2",
      "p0510311" => "New-kana",
      "p0510264" => "westwood",
      "p0649560" => "westmin",
      "p0621877" => "waybury",
      "p0510263" => "water",
      "p1230122" => "nolan",
      "p0510936" => "village",
      "p0510262" => "victoria",
      "p0848212" => "thevibe",
      "p0510194" => "3ver",
      "p0510261" => "venus",
      "p0510934" => "torbay",
      "p0649563" => "tisbury",
      "p0518795" => "300royal",
      "p0895115" => "treo",
      "p1522350" => "rivers",
      "p0510302" => "plaza",
      "p1108595" => "thelink",
      "p0510305" => "thelind",
      "p0736213" => "killick",
      "p1343743" => "thekay",
      "p1411841" => "heart",
      "p1745714" => "governor",
      "p1359246" => "188marg",
      "p1812905" => "carrick",
      "p0510304" => "200royal",
      "p0510245" => "anchor",
      "p0621874" => "alex",
      "p0887014" => "S2",
      "p0510260" => "terrace",
      "p0510259" => "sydney",
      "p0621872" => "spruce",
      "p0510271" => "spring",
      "p0510201" => "sgt",
      "p0545517" => "sport",
      "p0510258" => "south",
      "p0510315" => "rosen",
      "p0510282" => "stjames",
      "p0510321" => "280shake",
      "p0510248" => "pw",
      "p0510246" => "moxham",
      "p0510320" => "kristin",
      "p0510268" => "edward",
      "p0510325" => "ducks2",
      "p0510322" => "ducks1",
      "p0510220" => "country",
      "p0510213" => "cabot",
      "p0510211" => "browns",
      "p0510209" => "altkel",
      "p1509677" => "woolwich",
      "p0510196" => "505-525",
      "p0510190" => "27long",
      "p0510316" => "mayview",
      "p0892457" => "shore",
      "p0510257" => "sheradon",
      "p0510256" => "shaun",
      "p0510255" => "shake",
      "p0735816" => "saginaw2",
      "p0521705" => "saginaw",
      "p0510306" => "s2",
      "p0510945" => "rutledge",
      "p0510254" => "rocky",
      "p0510289" => "200eagle",
      "p1359245" => "ridsom",
      "p0511140" => "ridge",
      "p0510284" => "richill",
      "p0510253" => "qt",
      "p0510252" => "qc",
      "p1490727" => "quiet",
      "p0510299" => "133ked",
      "p0510298" => "115ked",
      "p0510278" => "princess",
      "p0510938" => "pleasant",
      "p0510250" => "pineglen",
      "p0510249" => "pheasant",
      "p0510285" => "paxton",
      "p0510226" => "456park",
      "p0551361" => "parker",
      "p0510280" => "oneoak",
      "p0510329" => "north",
      "p1359248" => "norgar",
      "p1660034" => "nolan2",
      "p0510247" => "nevada",
      "p1415018" => "nautical",
      "p0510930" => "mount",
      "p0658833" => "housemap",
      "p0510244" => "mh",
      "p0510242" => "lutz",
      "p1367522" => "luma",
      "p0510241" => "lorentz",
      "p0510240" => "linden",
      "p0519304" => "305rey",
      "p1230114" => "leopold",
      "p1074522" => "diepvill",
      "p1077027" => "nuvo",
      "p0510300" => "135gould",
      "p1339159" => "latitude",
      "p0510238" => "lake",
      "p0510239" => "lf",
      "p0510236" => "kent",
      "p0510235" => "kensing",
      "p0510234" => "kendra",
      "p0510232" => "horton",
      "p1252229" => "88Sun",
      "p0510231" => "hillcrst",
      "p0511094" => "11h-293c",
      "p1359242" => "heritage",
      "p0519221" => "g5",
      "p0510223" => "forest",
      "p0510286" => "glengate",
      "p0510324" => "777gauv",
      "p0510292" => "new-grdn",
      "p0981045" => "frontier",
      "p0510928" => "fresh",
      "p0510224" => "fort",
      "p0510926" => "fmanor",
      "p0510277" => "fhill",
      "p0621876" => "trafair",
      "p1392793" => "emma",
      "p0510332" => "grnpark",
      "p0510222" => "eller",
      "p0510301" => "eaglerid",
      "p0510221" => "dillman",
      "p0510210" => "belved",
      "p1405226" => "140dale",
      "p1522341" => "621crown",
      "p1367519" => "belmont",
      "p1500930" => "craig",
      "p0510924" => "cornwall",
      "p1543512" => "civic66",
      "p1149360" => "christie",
      "p0557279" => "chelsea",
      "p0510297" => "155can",
      "p0510291" => "charlott",
      "p0510308" => "chapter",
      "p0510215" => "cameron",
      "p0510218" => "cg",
      "p0510275" => "carhouse",
      "p1739313" => "carlhous",
      "p0510217" => "carleton",
      "p0510328" => "camarm",
      "p0510216" => "camplace",
      "p0510214" => "camcourt",
      "p0510212" => "burns2",
      "p0510270" => "buck",
      "p0510207" => "bruce",
      "p0510317" => "brighton",
      "p0510309" => "brent",
      "p0510283" => "bluerock",
      "p0560808" => "20tech",
      "p0510940" => "black2",
      "p0510947" => "bennett",
      "p0510287" => "belmar",
      "p0621875" => "bellwood",
      "p0510206" => "969reg",
      "p0510205" => "95knight",
      "p0510208" => "9syb",
      "p0510228" => "gordon",
      "p1149285" => "9carr",
      "p0510204" => "85-127",
      "p0510237" => "knight",
      "p0510203" => "75green",
      "p0510202" => "67-141",
      "p0510312" => "668fhill",
      "p0510330" => "65bon",
      "p0510314" => "cummings",
      "p0510274" => "6101s",
      "p0510273" => "6087s",
      "p0510233" => "jamie",
      "p0510227" => "gf",
      "p0510200" => "57west",
      "p1476242" => "54ass",
      "p0510199" => "53som",
      "p0510198" => "tobin",
      "p0510251" => "queen",
      "p0510267" => "50g-190p",
      "p0510197" => "50bark",
      "p1681980" => "5-35harl",
      "p0848218" => "59harley",
      "p1681979" => "48-66",
      "p0510303" => "strath",
      "p0510195" => "458lutz",
      "p1348773" => "38pas",
      "p0510281" => "37som",
      "p0510269" => "364-368",
      "p0510193" => "360a",
      "p0510323" => "36west",
      "p0510219" => "conn",
      "p0848220" => "34mted",
      "p0510192" => "316a",
      "p0510272" => "31carr",
      "p0510191" => "303n",
      "p0519216" => "300rey",
      "p0614692" => "300inn",
      "p0848219" => "297allen",
      "p0510243" => "main",
      "p0510225" => "gauvin",
      "p0743626" => "270park",
      "p0510189" => "260wet",
      "p0614690" => "246inn",
      "p0510188" => "bedford",
      "p0510187" => "23glen",
      "p0510276" => "21park",
      "p0510290" => "19plat",
      "p0510307" => "new-180m",
      "p0510186" => "175-211",
      "p0510185" => "159rad",
      "p0649561" => "stoney",
      "p1440114" => "155ked",
      "p0510319" => "1546carl",
      "p0510318" => "1540carl",
      "p0875058" => "151green",
      "p1082215" => "150lian",
      "p1586933" => "1477carl",
      "p1113336" => "145can",
      "p1498394" => "1360holl",
      "p1066733" => "1355sil",
      "p1509469" => "1350holl",
      "p0521776" => "hollis",
      "p1191013" => "1325holl",
      "p0510184" => "127-157",
      "p0521775" => "125k",
      "p0510265" => "wilsey",
      "p0510183" => "1111main",
      "p1059449" => "11harold",
      "p0510942" => "meadow",
      "p0510327" => "1033que",
      "p0510182" => "101arch",
      "p0510326" => "ossing",
      "p0510288" => "100eagle",
      "p0510181" => "100arch",
      "p0510230" => "harl",
      "p0848215" => "10harley",
      "p1514717" => "luma",
    ];
  }

}
