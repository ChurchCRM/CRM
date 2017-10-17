<?php

namespace ChurchCRM\Utils;

class MiscUtils {
 

  public static function random_word( $length = 6 ) {
      $cons = array( 'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'z', 'pt', 'gl', 'gr', 'ch', 'ph', 'ps', 'sh', 'st', 'th', 'wh' );
      $cons_cant_start = array( 'ck', 'cm', 'dr', 'ds','ft', 'gh', 'gn', 'kr', 'ks', 'ls', 'lt', 'lr', 'mp', 'mt', 'ms', 'ng', 'ns','rd', 'rg', 'rs', 'rt', 'ss', 'ts', 'tch');
      $vows = array( 'a', 'e', 'i', 'o', 'u', 'y','ee', 'oa', 'oo');
      $current = ( mt_rand( 0, 1 ) == '0' ? 'cons' : 'vows' );
      $word = '';
      while( strlen( $word ) < $length ) {
          if( strlen( $word ) == 2 ) $cons = array_merge( $cons, $cons_cant_start );
          $rnd = ${$current}[ mt_rand( 0, count( ${$current} ) -1 ) ];
          if( strlen( $word . $rnd ) <= $length ) {
              $word .= $rnd;
              $current = ( $current == 'cons' ? 'vows' : 'cons' );
          }
      }
      return $word;
  }

}
?>