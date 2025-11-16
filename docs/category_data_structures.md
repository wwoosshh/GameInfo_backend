# 카테고리별 데이터 구조

각 업데이트 항목 카테고리별로 `additional_data` JSON 필드에 저장되는 데이터 구조입니다.

## 1. 신규 필드 (new_field)

맵/지역 정보

```json
{
  "name": "영원의 거룩한 도시 오크마",
  "name_en": "Okhema, the Eternal Holy City",
  "region": "앰포리어스",
  "sub_region": "오크마"
}
```

## 2. 신규 운명의 길 (new_path)

직업 정보

```json
{
  "name": "기억",
  "role": "기타",
  "taunt_value": 100,
  "characteristics": "추가 인원을 소환"
}
```

**역할군 옵션**: 딜러, 공격형 서포터, 방어형 서포터, 기타

## 3. 신규/복각 캐릭터 (new_character, rerun_character)

```json
{
  "name": "로빈",
  "nickname": "꾀꼬리",
  "gender": "여성",
  "affiliation": "페나코니",
  "element": "물리",
  "path": "화합",
  "rarity": "5성",
  "name_ko": "로빈",
  "name_en": "Robin",
  "name_cn": "知更鸟",
  "name_jp": "ロビン",
  "voice_actor_ko": "이보희",
  "voice_actor_en": "Alice Himora",
  "voice_actor_cn": "王晓彤",
  "voice_actor_jp": "釘宮理恵"
}
```

**속성 옵션**: 물리, 화염, 얼음, 번개, 바람, 양자, 허수
**성급 옵션**: 4성, 5성

## 4. 신규/복각 광추 (new_lightcone, rerun_lightcone)

무기 정보

```json
{
  "name": "시간은 꿈을 잊지 않아",
  "path": "화합",
  "rarity": "5성",
  "base_hp": 1164,
  "base_atk": 582,
  "base_def": 463,
  "skill_name": "에코와 환상",
  "skill_description": "장착자의 에너지 재생 효율이 8/10/12/14/16% 증가한다...",
  "acquisition_method": "한정 뽑기",
  "name_ko": "시간은 꿈을 잊지 않아",
  "name_en": "Yet Hope Is Priceless",
  "name_cn": "时节不居",
  "name_jp": "時は夢を忘れない"
}
```

**성급 옵션**: 3성, 4성, 5성

## 5. 신규 유물 (new_relic)

장비 세트 정보

```json
{
  "name": "무사 시바의 검투 경기장",
  "type": "차원 장신구",
  "set_2pc_effect": "치명타 확률이 8% 증가한다.",
  "set_4pc_effect": null,
  "acquisition_method": "우주 교역",
  "name_ko": "무사 시바의 검투 경기장",
  "name_en": "Salsotto's Gladiatorial Arena",
  "name_cn": "萨尔索图的斗技场",
  "name_jp": "剣闘士サルソットの闘技場"
}
```

**유형 옵션**: 유물, 차원 장신구

## 6. 신규 개척 임무 (new_trailblaze)

메인 스토리 임무

```json
{
  "name": "리옥의 고행길",
  "chapter_number": "3막",
  "subtitle": "앰포리어스의 갈등",
  "unlock_condition": "개척 레벨 40",
  "description": "앰포리어스에서 벌어지는 신들의 전쟁에 휘말린 개척자는..."
}
```

## 7. 신규 동행 임무 (new_companion)

캐릭터 스토리 임무

```json
{
  "name": "로빈의 꿈",
  "chapter_number": "1막",
  "subtitle": "노래하는 새",
  "unlock_condition": "로빈 획득",
  "description": "로빈과 함께하는 페나코니 여행..."
}
```

## 8. 신규 모험 임무 (new_adventure)

NPC 스토리 임무

```json
{
  "name": "우주 상인의 고민",
  "chapter_number": "단막",
  "subtitle": "",
  "unlock_condition": "개척 레벨 21",
  "description": "정거장의 상인이 도움을 요청한다..."
}
```

## 9. 신규 코스튬 (new_costume)

```json
{
  "name": "별빛 무대",
  "character_name": "로빈",
  "acquisition_method": "이벤트 보상",
  "release_version": "2.7"
}
```

## 10. 신규 이벤트 (new_event)

```json
{
  "name": "꿈의 축제",
  "event_type": "이벤트 스토리",
  "description": "페나코니에서 펼쳐지는 축제 이벤트",
  "rewards": "성옥 x1600, 광추 재료"
}
```

**이벤트 유형 옵션**: 지급성, 이벤트 스토리, 홈페이지, 미니게임

## 11. 혜택 및 육성 지원 (support_event)

```json
{
  "name": "신규 개척자 지원",
  "support_type": "재화 획득 버프",
  "description": "7일간 경험치 획득량 2배",
  "duration": "7일"
}
```

## 12. 신규 콘텐츠 (new_content)

```json
{
  "name": "순수 허구",
  "content_type": "영구 콘텐츠",
  "description": "새로운 도전 던전 시스템"
}
```

**콘텐츠 유형**: 시스템, 영구 콘텐츠

## 13. 신규 적 (new_enemy)

```json
{
  "name": "코코리아",
  "nickname": "대수호자",
  "affiliation": "야릴로-VI",
  "element_weakness": ["물리", "화염", "번개"],
  "description": "벨로보그의 대수호자",
  "resistance": {
    "physical": 20,
    "fire": 20,
    "ice": 40,
    "lightning": 20,
    "wind": 20,
    "quantum": 20,
    "imaginary": 20
  },
  "debuff_resistance": {
    "freeze": 50,
    "imprison": 30
  },
  "skills": [
    {
      "skill_name": "동결의 창",
      "target": "단일",
      "skill_type": "공격",
      "description": "대상에게 얼음 속성 피해를 입힌다",
      "speed": 120
    }
  ]
}
```

## 14. 신규 재료 (new_material)

```json
{
  "name": "생명의 싹",
  "usage": "캐릭터 승급",
  "rarity": "3성",
  "description": "풍요의 힘이 담긴 재료",
  "acquisition_method": "의태 꽃 보스",
  "used_by_characters": ["나타샤", "루안 메이", "갈라거"]
}
```

**성급 옵션**: 1성, 2성, 3성, 4성, 5성

## 15. 편의성 업데이트 (convenience)

```json
{
  "title": "유물 최적화 기능 추가",
  "description": "자동으로 최적의 유물 조합을 추천하는 기능"
}
```

## 16. 기타 (other)

```json
{
  "title": "기타 업데이트",
  "description": "버그 수정 및 안정화"
}
```
