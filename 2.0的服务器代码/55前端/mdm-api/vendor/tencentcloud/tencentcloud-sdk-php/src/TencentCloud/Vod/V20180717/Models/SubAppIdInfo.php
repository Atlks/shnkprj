<?php
/*
 * Copyright (c) 2017-2018 THL A29 Limited, a Tencent company. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace TencentCloud\Vod\V20180717\Models;
use TencentCloud\Common\AbstractModel;

/**
 * @method integer getSubAppId() 获取子应用 ID。
 * @method void setSubAppId(integer $SubAppId) 设置子应用 ID。
 * @method string getName() 获取子应用名称。
 * @method void setName(string $Name) 设置子应用名称。
 * @method string getDescription() 获取子应用简介。
 * @method void setDescription(string $Description) 设置子应用简介。
 * @method string getCreateTime() 获取子应用创建时间，采用 [ISO 日期格式](https://cloud.tencent.com/document/product/266/11732#iso-.E6.97.A5.E6.9C.9F.E6.A0.BC.E5.BC.8F)。
 * @method void setCreateTime(string $CreateTime) 设置子应用创建时间，采用 [ISO 日期格式](https://cloud.tencent.com/document/product/266/11732#iso-.E6.97.A5.E6.9C.9F.E6.A0.BC.E5.BC.8F)。
 * @method string getStatus() 获取子应用状态，有效值：
<li>On：启用；</li>
<li>Off：停用。</li>
 * @method void setStatus(string $Status) 设置子应用状态，有效值：
<li>On：启用；</li>
<li>Off：停用。</li>
 */

/**
 *子应用信息。
 */
class SubAppIdInfo extends AbstractModel
{
    /**
     * @var integer 子应用 ID。
     */
    public $SubAppId;

    /**
     * @var string 子应用名称。
     */
    public $Name;

    /**
     * @var string 子应用简介。
     */
    public $Description;

    /**
     * @var string 子应用创建时间，采用 [ISO 日期格式](https://cloud.tencent.com/document/product/266/11732#iso-.E6.97.A5.E6.9C.9F.E6.A0.BC.E5.BC.8F)。
     */
    public $CreateTime;

    /**
     * @var string 子应用状态，有效值：
<li>On：启用；</li>
<li>Off：停用。</li>
     */
    public $Status;
    /**
     * @param integer $SubAppId 子应用 ID。
     * @param string $Name 子应用名称。
     * @param string $Description 子应用简介。
     * @param string $CreateTime 子应用创建时间，采用 [ISO 日期格式](https://cloud.tencent.com/document/product/266/11732#iso-.E6.97.A5.E6.9C.9F.E6.A0.BC.E5.BC.8F)。
     * @param string $Status 子应用状态，有效值：
<li>On：启用；</li>
<li>Off：停用。</li>
     */
    function __construct()
    {

    }
    /**
     * 内部实现，用户禁止调用
     */
    public function deserialize($param)
    {
        if ($param === null) {
            return;
        }
        if (array_key_exists("SubAppId",$param) and $param["SubAppId"] !== null) {
            $this->SubAppId = $param["SubAppId"];
        }

        if (array_key_exists("Name",$param) and $param["Name"] !== null) {
            $this->Name = $param["Name"];
        }

        if (array_key_exists("Description",$param) and $param["Description"] !== null) {
            $this->Description = $param["Description"];
        }

        if (array_key_exists("CreateTime",$param) and $param["CreateTime"] !== null) {
            $this->CreateTime = $param["CreateTime"];
        }

        if (array_key_exists("Status",$param) and $param["Status"] !== null) {
            $this->Status = $param["Status"];
        }
    }
}
