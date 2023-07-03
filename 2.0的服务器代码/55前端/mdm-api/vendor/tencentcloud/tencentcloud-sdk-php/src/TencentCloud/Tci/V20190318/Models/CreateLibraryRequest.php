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
namespace TencentCloud\Tci\V20190318\Models;
use TencentCloud\Common\AbstractModel;

/**
 * @method string getLibraryName() 获取人员库名称
 * @method void setLibraryName(string $LibraryName) 设置人员库名称
 * @method string getLibraryId() 获取人员库唯一标志符，为空则系统自动生成。
 * @method void setLibraryId(string $LibraryId) 设置人员库唯一标志符，为空则系统自动生成。
 */

/**
 *CreateLibrary请求参数结构体
 */
class CreateLibraryRequest extends AbstractModel
{
    /**
     * @var string 人员库名称
     */
    public $LibraryName;

    /**
     * @var string 人员库唯一标志符，为空则系统自动生成。
     */
    public $LibraryId;
    /**
     * @param string $LibraryName 人员库名称
     * @param string $LibraryId 人员库唯一标志符，为空则系统自动生成。
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
        if (array_key_exists("LibraryName",$param) and $param["LibraryName"] !== null) {
            $this->LibraryName = $param["LibraryName"];
        }

        if (array_key_exists("LibraryId",$param) and $param["LibraryId"] !== null) {
            $this->LibraryId = $param["LibraryId"];
        }
    }
}
