# database
SQL Builder With PDO, ORM etc

## 注意 Model 关联查询不支持 方法名驼峰转化

`$model->option_items` 不会调用 `$model->optionItems()`

## 提前预知为空不进行真实数据请求

```php
$query->isEmpty();
```

## model 判断是否为空 请不要轻易用 empty 关联关系时无法正确判断


```php

$model = new Model();

$model->setRaelation('a', 122222);


empty($model['a'])  // true

$model->relationLoaded('a') // true


```


## 关联的特殊用法

根据商品获取商品属性及属性名，

```php

$data = [
    'id' => 1,
    'name' => 2
];
$data = Relation::create($data, [
    't' => [
        'query' => GoodsAttributeModel::query(),
        'link' => ['id', 'goods_id'],
        'relation' => [
            'attr' => [
                'query' => AttributeModel::query(),
                'type' => 0,
                'link' => ['attribute_id', 'id'],
            ]
        ]
    ],
]);

```

最终结果如下

```json

{
  "id": 1,
  "name": 2,
  "t": [
    {
      "id": 10000,
      "goods_id": 1,
      "value": "11111",
      "attribute_id": 5,
      "attr": {
        "id": 5,
        "name": "尺寸"
      }
    }
  ]
}

```

多级如果关联名为空则替换上一级，例如

```php

$data = [
    'id' => 1,
    'name' => 2
];
$data = Relation::create($data, [
    't' => [
        'query' => GoodsAttributeModel::query(),
        'link' => ['id', 'goods_id'],
        'relation' => [
            [
                'query' => AttributeModel::query(),
                'type' => 0,
                'link' => ['attribute_id', 'id'],
            ]
        ]
    ],
]);

```

最终结果如下

```json

{
  "id": 1,
  "name": 2,
  "t": [
    {
        "id": 5,
        "name": "尺寸"
    }
  ]
}

```