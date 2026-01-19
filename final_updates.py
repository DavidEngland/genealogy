#!/usr/bin/env python3
"""Final updates to The Shoals.md"""

def final_updates():
    with open('The Shoals.md', 'r', encoding='utf-8') as f:
        content = f.read()
    
    # 1. Update Chickasaws and Cherokees intro - match exact spacing
    if "     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees.     The Cherokees were the largest" in content:
        content = content.replace(
            "     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees.     The Cherokees were the largest",
            "     To examine native claims on the Shoals area is to look at the Chickasaws and Cherokees, though understanding their presence requires acknowledging the complex web of Indigenous relationships that predated colonial boundaries.\n     The Cherokees were the largest"
        )
        print("✓ Updated Chickasaws and Cherokees introduction")
    
    # 2. Update Colbert section - add Scottish heritage emphasis
    if '     In addition to moving the Trace route, Colbert negotiated the construction of a home, outbuildings and a ferryboat for his business.     Arrell M. Gibson, author of "The Chickasaws," said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, who, according to Trace historian Dawsan A. Phelps, was a Scotsman living among the Chickasaws as early as 1767.' in content:
        content = content.replace(
            '     In addition to moving the Trace route, Colbert negotiated the construction of a home, outbuildings and a ferryboat for his business.     Arrell M. Gibson, author of "The Chickasaws," said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, who, according to Trace historian Dawsan A. Phelps, was a Scotsman living among the Chickasaws as early as 1767.',
            '     In addition to moving the Trace route, Colbert negotiated the construction of a home, outbuildings and a ferryboat for his business.\nThe Scottish Colbert Family and Indigenous Leadership\n     Arrell M. Gibson, author of "The Chickasaws," said that by 1800 mixed bloods dominated the Chickasaw tribe and the Colberts were the most dominant.     The rise of the Colberts can be attributed to James Logan Colbert, a Scottish trader who, according to Trace historian Dawsan A. Phelps, was living among the Chickasaws as early as 1767.     James Logan Colbert\'s integration into Chickasaw society through marriage to Chickasaw women created a family that would bridge two worlds, becoming powerful leaders within the Chickasaw nation while maintaining connections to Euro-American commerce and politics.'
        )
        print("✓ Added Scottish Colbert Family section")
    
    # 3. Update Colbert sons
    if '"He had lived among the Indians for 40 years and had a rich holding among the Chickasaws, 150 Negro slaves and several sons by Chickasaw women."     Colbert had at least five sons who all rose to prominence in the tribe.     The eldest was reputed to be George' in content:
        content = content.replace(
            '"He had lived among the Indians for 40 years and had a rich holding among the Chickasaws, 150 Negro slaves and several sons by Chickasaw women."     Colbert had at least five sons who all rose to prominence in the tribe.     The eldest was reputed to be George',
            '"He had lived among the Indians for 40 years and had a rich holding among the Chickasaws, 150 Negro slaves and several sons by Chickasaw women."     Colbert had at least five sons who all rose to prominence in the tribe, becoming influential leaders who navigated the complex political landscape between Indigenous sovereignty and American expansion.\n     The eldest was reputed to be George'
        )
        print("✓ Updated Colbert sons description")
    
    # 4. Add ferry infrastructure
    if ", who was described as being largely illiterate, shrewd and possessing the ability to make money. He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     Colbert became widely known for the high prices" in content:
        content = content.replace(
            ", who was described as being largely illiterate, shrewd and possessing the ability to make money. He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     Colbert became widely known for the high prices",
            ", who was described as being largely illiterate, shrewd and possessing the ability to make money. He moved to the banks of the Tennessee in 1801 and operated a plantation and ferry on the site know as Georgetown until 1819.     George Colbert's ferry became essential infrastructure at the Shoals crossing, serving travelers on the Natchez Trace and facilitating the movement of people and goods through Chickasaw territory.\n     Colbert became widely known for the high prices"
        )
        print("✓ Added ferry infrastructure context")
    
    # 5. Update Donelson expedition
    if "These may have been the first American families to use McFarland Bottoms as a campground.     After four months and more than one thousand miles" in content:
        content = content.replace(
            "These may have been the first American families to use McFarland Bottoms as a campground.     After four months and more than one thousand miles",
            "These may have been the first American families to use McFarland Bottoms as a campground—though Indigenous peoples had used these crossing points and camp sites for thousands of years.     The Shoals and areas downriver as far as Waterloo had long served as major crossing points, and Waterloo would later become a tragic departure point during the Trail of Tears.\n     After four months and more than one thousand miles"
        )
        print("✓ Updated Donelson expedition context")
    
    # Write the file
    with open('The Shoals.md', 'w', encoding='utf-8') as f:
        f.write(content)
    
    print("\n✓ All updates complete!")

if __name__ == '__main__':
    final_updates()
