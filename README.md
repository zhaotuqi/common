# 公共依赖类库
    1 日志重写
    2 公共类方法
    3 依赖guzzle
    4 新增env配置
    5 新增结算平台（用于算准课耗等）
    
## 结算平台--ID生成器

```dotenv

```
## 计算平台使用
```php
     

     use App\Libraries\SettlementPlatformIdGernerator;
     
     app(SettlementPlatformIdGernerator::class)->IdGenerator();
     
```