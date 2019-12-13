# database
SQL Builder With PDO, ORM etc

## 提前预知为空不进行真实数据请求

```php
$query->isEmpty();
```

## 关联的特殊用法

根据商品获取商品属性及属性名

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
