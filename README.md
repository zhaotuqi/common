# 公共依赖类库
    1 日志重写
    2 公共类方法
    3 依赖guzzle
    4 新增env配置
    5 新增结算平台ID生成器（用于算准课耗等）
    
## 结算平台--ID生成器配置 .env 现在没啥用了

##  gitlab webhook test
```


#prod  正式环境ID生成器配置
RTC_BASE_UUID_SERVICE_URI='http://10.10.31.38/uuid/settlement'
RTC_BASE_UUID_APP_ID='butian'
RTC_BASE_UUID_APP_KEY='d3c61cacab140b3bd'

#qa 测试环境ID生成器配置
RTC_BASE_UUID_SERVICE_URI='http://10.2.1.107:8079/uuid/settlement'
RTC_BASE_UUID_APP_ID='test2'
RTC_BASE_UUID_APP_KEY='keytest2'
    
    
```

## 计算平台--ID生成器PHP调用
```php

     use App\Libraries\SettlementPlatformIdGernerator;
     
     app(SettlementPlatformIdGernerator::class)->IdGenerator();
     
     
```