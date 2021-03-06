<?php
/**
*查询商家产品类别列表(店铺类别)
*/

class CategoryMerchantProductsGetRequest {

    private $isCompatible = 0;
    
	private $apiParas = array();
		
	public function getApiParas()
	{
		return $this->apiParas;
	}

	public function getApiMethodName()
	{
		return "yhd.category.merchant.products.get";
	}
	
	/**父类别ID（0：根节点）*/
	private  $categoryParentId; 

	public function getCategoryParentId(){
		return $this->categoryParentId;
	}

	public function setCategoryParentId($categoryParentId){
		$this->categoryParentId = $categoryParentId;
		$this->apiParas["categoryParentId"] = $categoryParentId;
	}

	public function getIsCompatible(){
		return $this->isCompatible;
	}
}
