<?php
/**
 * LMLPHP Framework
 * Copyright (c) 2014 http://lmlphp.com All rights reserved.
 * Licensed ( http://mit-license.org/ )
 * Author: leiminglin <leiminglin@126.com>
 *
 * A fully object-oriented PHP framework.
 * Keep it light, magnificent, lovely.
 *
 */

class Paging{
	
	// all count
	private $count;
	// current page no.
	private $current;
	// per page items count
	private $pcount;
	// all pages count
	private $pages;
	
	public function __construct($count, $current, $pcount=20){
		$this->count = $count;
		$this->pcount = $pcount;
		$this->pages = ceil($count/$pcount);
		$this->current = $current;
		if($current<1){
			$this->current = 1;
		}elseif($current>$this->pages){
			$this->current = $this->pages;
		}
	}
	
	/**
	 * get all pages NO.
	 * 
	 * @return multitype:array
	 */
	public function getAllPids(){
		return range(1, $this->pages);
	}
	
	/**
	 * get $len page NO.
	 * @param number $len
	 * @return Ambigous <multitype:array>
	 */
	public function getPids($len=0){
		if( $len ){
			if($len >= $this->pages){
				return $this->getAllPids();
			}else{
				$clen=$len;
				$ret = array($this->current);
				$len--;
				$leftc = ceil( ($len)/2 );
				$rightc = $len - $leftc;
				$direction = 0;
				for ($i=0;$i<$leftc;$i++){
					$temp = reset($ret)-1;
					if( $temp > 0 ){
						array_unshift($ret, $temp);
						$len--;
					}else{
						$direction = 2;
						break;
					}
				}
				for ($i=0;$i<$rightc;$i++){
					$temp = end($ret)+1;
					if( $temp <= $this->pages ){
						array_push($ret, $temp);
						$len--;
					}else{
						$direction = 1;
						break;
					}
				}
				if($direction==1){
					$min = $ret[0];
					for($i=0; $i<$len; $i++) {
						array_unshift($ret, --$min);
					}
				}
				if($direction==2){
					$max = end($ret);
					for($i=0; $i<$len; $i++) {
						array_push($ret, ++$max);
					}
				}
				// add first and last page NO.
				$ret[0]==1?'':($ret[0]=1);
				end($ret)==$this->pages?'':($ret[$clen-1]=$this->pages);
				return $ret;
			}
		}
		return $this->getAllPids();
	}
	
	/**
	 * get previous page NO.
	 * @return number
	 */
	public function getPrev(){
		return $this->hasPrev()?$this->current-1:1;
	}
	
	/**
	 * get next page NO.
	 * @return number
	 */
	public function getNext(){
		return $this->hasNext()?$this->current+1:$this->pages;
	}
	
	/**
	 * check whether has previous
	 * @return boolean
	 */
	public function hasPrev(){
		return $this->current>1;
	}
	
	/**
	 * check whether has next
	 * @return boolean
	 */
	public function hasNext(){
		return $this->current<$this->pages;
	}
	
	/**
	 * get page count
	 * @return int
	 */
	public function getPageCount(){
		return $this->pages;
	}
	
}